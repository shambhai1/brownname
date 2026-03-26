<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

startUserSession();

if (isUserLoggedIn()) {
    header('Location: account.php');
    exit;
}

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '' || GOOGLE_REDIRECT_URI === '') {
    header('Location: login.php?error=' . urlencode('Google login is not configured yet.'));
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
