<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function startUserSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function isUserLoggedIn(): bool
{
    startUserSession();
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function getCurrentUser(): ?array
{
    startUserSession();
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function loginUser(array $user): void
{
    startUserSession();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
    ];
}

function updateCurrentUserSession(array $user): void
{
    loginUser($user);
}

function logoutUser(): void
{
    startUserSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function isAdminLoggedIn(): bool
{
    startUserSession();
    return isset($_SESSION['admin']) && is_array($_SESSION['admin']);
}

function getCurrentAdmin(): ?array
{
    startUserSession();
    $admin = $_SESSION['admin'] ?? null;
    return is_array($admin) ? $admin : null;
}

function loginAdmin(string $email): void
{
    startUserSession();
    $_SESSION['admin'] = [
        'email' => $email,
    ];
}

function logoutAdmin(): void
{
    startUserSession();
    unset($_SESSION['admin']);
}

function requireUserLogin(): void
{
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
