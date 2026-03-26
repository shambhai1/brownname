<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startUserSession();

if (isAdminLoggedIn()) {
    header('Location: applications.php');
    exit;
}

$success = '';
$error = '';

function ensureAdminUsersTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(140) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_admin_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'login'));

    try {
        $pdo = getConnection();
        ensureAdminUsersTable($pdo);

        if ($action === 'register') {
            $email = trim((string) ($_POST['register_email'] ?? ''));
            $password = (string) ($_POST['register_password'] ?? '');
            $confirmPassword = (string) ($_POST['register_confirm_password'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid administrator email address.';
            } elseif (strlen($password) < 6) {
                $error = 'Administrator password must be at least 6 characters.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Administrator password and confirm password do not match.';
            } else {
                $checkStmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
                $checkStmt->execute(['email' => $email]);

                if ($checkStmt->fetch()) {
                    $error = 'An administrator account with this email already exists.';
                } else {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO admin_users (email, password_hash, created_at)
                         VALUES (:email, :password_hash, NOW())'
                    );
                    $insertStmt->execute([
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $success = 'Administrator account created. You can login below.';
                }
            }
        } else {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid administrator email address.';
            } elseif ($password === '') {
                $error = 'Administrator password is required.';
            } else {
                $stmt = $pdo->prepare('SELECT id, email, password_hash FROM admin_users WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                $admin = $stmt->fetch();

                $isDefaultAdmin = ($email === ADMIN_EMAIL && $password === ADMIN_PASSWORD);
                $isDatabaseAdmin = $admin && password_verify($password, (string) $admin['password_hash']);

                if (!$isDefaultAdmin && !$isDatabaseAdmin) {
                    $error = 'Invalid administrator email or password.';
                } else {
                    loginAdmin($email);
                    header('Location: applications.php');
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        $error = 'Unable to process administrator request right now.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card auth-card-login">
            <div class="auth-head">
                <h1>Welcome Back!</h1>
                <p>Login to administrator account</p>
            </div>
            <?php if ($success !== ''): ?>
                <p class="message success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="action" value="login">
                <label>Email Address *
                    <input type="email" name="email" required placeholder="your.email@example.com">
                </label>
                <label>Password *
                    <div class="password-field">
                        <input type="password" name="password" id="adminPassword" required placeholder="********">
                        <button type="button" class="password-toggle" id="toggleAdminPassword" aria-label="Show password" aria-pressed="false">
                            <span class="eye-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                </label>
                <div class="auth-row">
                    <label class="checkbox-row">
                        <input type="checkbox" name="remember_admin">
                        <span>Remember Me</span>
                    </label>
                    <a class="auth-inline-link" href="../forgot-password.php">Forgot Password?</a>
                </div>
                <button class="btn-primary" type="submit">Login</button>
            </form>
            <div class="auth-divider"><span>OR</span></div>
            <form method="post" class="auth-form admin-create-form">
                <input type="hidden" name="action" value="register">
                <label>Create Admin Email *
                    <input type="email" name="register_email" required placeholder="admin@example.com">
                </label>
                <label>Create Password *
                    <div class="password-field">
                        <input type="password" name="register_password" id="adminRegisterPassword" required minlength="6" placeholder="Create password">
                        <button type="button" class="password-toggle" id="toggleAdminRegisterPassword" aria-label="Show password" aria-pressed="false">
                            <span class="eye-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                </label>
                <label>Confirm Password *
                    <div class="password-field">
                        <input type="password" name="register_confirm_password" id="adminRegisterConfirmPassword" required minlength="6" placeholder="Confirm password">
                        <button type="button" class="password-toggle" id="toggleAdminRegisterConfirmPassword" aria-label="Show password" aria-pressed="false">
                            <span class="eye-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                </label>
                <button class="btn-secondary" type="submit">Create Administrator Account</button>
            </form>
            <p class="auth-link auth-link-center"><a href="../index.php">Back to Home</a></p>
        </section>
    </main>
    <script>
        (function () {
            const bindToggle = function (inputId, buttonId) {
                const input = document.getElementById(inputId);
                const button = document.getElementById(buttonId);

                if (!input || !button) {
                    return;
                }

                button.addEventListener('click', function () {
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                    button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    button.classList.toggle('is-visible', isHidden);
                });
            };

            bindToggle('adminPassword', 'toggleAdminPassword');
            bindToggle('adminRegisterPassword', 'toggleAdminRegisterPassword');
            bindToggle('adminRegisterConfirmPassword', 'toggleAdminRegisterConfirmPassword');
        }());
    </script>
</body>
</html>
