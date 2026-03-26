<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$email = trim((string) ($_GET['email'] ?? ''));
$result = null;
$error = '';

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            $pdo = getConnection();
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS admin_users (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(140) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_admin_users_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $studentStmt = $pdo->prepare('SELECT id, full_name, email, created_at FROM users WHERE email = :email LIMIT 1');
            $studentStmt->execute(['email' => $email]);
            $student = $studentStmt->fetch();

            $adminStmt = $pdo->prepare('SELECT id, email, created_at FROM admin_users WHERE email = :email LIMIT 1');
            $adminStmt->execute(['email' => $email]);
            $admin = $adminStmt->fetch();

            $result = [
                'student_exists' => (bool) $student,
                'student' => $student ?: null,
                'admin_exists' => (bool) $admin,
                'admin' => $admin ?: null,
            ];
        } catch (Throwable $e) {
            $error = 'Unable to check email right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Check | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card auth-card-login">
            <div class="auth-head">
                <h1>Email Check</h1>
                <p>Check whether an email exists in student or administrator accounts</p>
            </div>
            <?php if ($error !== ''): ?>
                <p class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="get" class="auth-form">
                <label>Email Address
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="your.email@example.com">
                </label>
                <button class="btn-primary" type="submit">Check Email</button>
            </form>

            <?php if (is_array($result)): ?>
                <div class="account-box" style="margin-top: 1rem;">
                    <p><strong>Student Account:</strong> <?php echo $result['student_exists'] ? 'Found' : 'Not Found'; ?></p>
                    <?php if (is_array($result['student'])): ?>
                        <p><strong>Student ID:</strong> #<?php echo (int) $result['student']['id']; ?></p>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars((string) $result['student']['full_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Created:</strong> <?php echo htmlspecialchars((string) $result['student']['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="account-box" style="margin-top: 1rem;">
                    <p><strong>Administrator Account:</strong> <?php echo $result['admin_exists'] ? 'Found' : 'Not Found'; ?></p>
                    <?php if (is_array($result['admin'])): ?>
                        <p><strong>Admin ID:</strong> #<?php echo (int) $result['admin']['id']; ?></p>
                        <p><strong>Created:</strong> <?php echo htmlspecialchars((string) $result['admin']['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="auth-link auth-link-center"><a href="login.php">Back to Login</a></p>
        </section>
    </main>
</body>
</html>
