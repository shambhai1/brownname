<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

startUserSession();

if (isUserLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$state = trim((string) ($_GET['state'] ?? ''));
$code = trim((string) ($_GET['code'] ?? ''));
$expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);

if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
    header('Location: login.php?error=' . urlencode('Google login failed. Invalid state.'));
    exit;
}

if ($code === '') {
    header('Location: login.php?error=' . urlencode('Google login failed. Missing authorization code.'));
    exit;
}

$tokenResponse = googleHttpPost('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code',
]);

if (!is_array($tokenResponse) || empty($tokenResponse['access_token'])) {
    header('Location: login.php?error=' . urlencode('Google login failed while requesting access token.'));
    exit;
}

$userInfo = googleHttpGet('https://www.googleapis.com/oauth2/v2/userinfo', (string) $tokenResponse['access_token']);

if (!is_array($userInfo) || empty($userInfo['email']) || empty($userInfo['name'])) {
    header('Location: login.php?error=' . urlencode('Google login failed while fetching profile.'));
    exit;
}

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => (string) $userInfo['email']]);
    $user = $stmt->fetch();

    if (!$user) {
        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, created_at)
             VALUES (:full_name, :email, :password_hash, NOW())'
        );
        $insert->execute([
            'full_name' => (string) $userInfo['name'],
            'email' => (string) $userInfo['email'],
            'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);

        $user = [
            'id' => (int) $pdo->lastInsertId(),
            'full_name' => (string) $userInfo['name'],
            'email' => (string) $userInfo['email'],
        ];
    }

    loginUser($user);
    header('Location: dashboard.php');
    exit;
} catch (Throwable $e) {
    header('Location: login.php?error=' . urlencode('Unable to login with Google right now.'));
    exit;
}

function googleHttpPost(string $url, array $payload): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($response) || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $json = json_decode($response, true);
    return is_array($json) ? $json : null;
}

function googleHttpGet(string $url, string $accessToken): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($response) || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $json = json_decode($response, true);
    return is_array($json) ? $json : null;
}
