<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

function ensurePasswordResetTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            email VARCHAR(140) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_password_resets_email (email),
            KEY idx_password_resets_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid reset request.';
    } elseif ($token === '') {
        $error = 'Missing reset token.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirm password do not match.';
    } else {
        try {
            $pdo = getConnection();
            ensurePasswordResetTable($pdo);
            $stmt = $pdo->prepare(
                'SELECT user_id, token_hash, expires_at
                 FROM password_resets
                 WHERE email = :email
                 LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $resetRow = $stmt->fetch();

            if (
                !$resetRow ||
                empty($resetRow['token_hash']) ||
                empty($resetRow['expires_at']) ||
                strtotime((string) $resetRow['expires_at']) < time() ||
                !password_verify($token, (string) $resetRow['token_hash'])
            ) {
                $error = 'Reset link is invalid or expired.';
            } else {
                $update = $pdo->prepare(
                    'UPDATE users
                     SET password_hash = :password_hash
                     WHERE id = :id'
                );
                $update->execute([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $resetRow['user_id'],
                ]);

                $delete = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id OR email = :email');
                $delete->execute([
                    'user_id' => $resetRow['user_id'],
                    'email' => $email,
                ]);

                $success = 'Password reset successfully. You can now login.';
            }
        } catch (Throwable $e) {
            $error = 'Unable to reset password right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card auth-card-login">
            <div class="auth-head">
                <h1>Reset Password</h1>
                <p>Set a new password for your account</p>
            </div>
            <?php if ($error !== ''): ?>
                <p class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <p class="message success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="auth-link auth-link-center"><a href="login.php">Go to Login</a></p>
            <?php else: ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <label>New Password *
                        <input type="password" name="password" required minlength="6">
                    </label>
                    <label>Confirm Password *
                        <input type="password" name="confirm_password" required minlength="6">
                    </label>
                    <button class="btn-primary" type="submit">Reset Password</button>
                </form>
                <p class="auth-link auth-link-center"><a href="login.php">Back to Login</a></p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
