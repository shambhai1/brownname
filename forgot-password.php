<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/otp.php';

session_start();

$error = '';
$success = '';
$devOtp = '';
$emailValue = '';
$resetSession = $_SESSION['password_reset_otp'] ?? null;
$showOtpForm = is_array($resetSession);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'request_otp');

    if ($action === 'request_otp') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $emailValue = $email;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            try {
                $pdo = getConnection();
                $studentStmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
                $studentStmt->execute(['email' => $email]);
                $account = $studentStmt->fetch();
                $accountType = 'student';

                if (!$account) {
                    $pdo->exec(
                        'CREATE TABLE IF NOT EXISTS admin_users (
                            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            email VARCHAR(140) NOT NULL UNIQUE,
                            password_hash VARCHAR(255) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            KEY idx_admin_users_email (email)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                    );

                    $adminStmt = $pdo->prepare('SELECT id, email FROM admin_users WHERE email = :email LIMIT 1');
                    $adminStmt->execute(['email' => $email]);
                    $account = $adminStmt->fetch();
                    $accountType = 'admin';
                }

                if (!$account) {
                    $error = 'No account found for this email address.';
                } else {
                    $otp = generateOtpCode();
                    $_SESSION['password_reset_otp'] = [
                        'user_id' => (int) $account['id'],
                        'email' => (string) $account['email'],
                        'account_type' => $accountType,
                        'code' => password_hash($otp, PASSWORD_DEFAULT),
                        'expires_at' => time() + OTP_EXPIRY_SECONDS,
                    ];

                    $sent = sendOtpEmail((string) $account['email'], $otp);
                    $showOtpForm = true;
                    $success = $sent
                        ? 'OTP sent to your email. Enter it below to reset your password.'
                        : 'OTP generated. Email delivery is not configured, so check storage/logs/otp.log.';
                    $devOtp = defined('OTP_DEV_EXPOSE_CODE') && OTP_DEV_EXPOSE_CODE ? $otp : '';
                }
            } catch (Throwable $e) {
                $error = 'Unable to process reset request right now.';
            }
        }
    } elseif ($action === 'resend_otp') {
        $resetSession = $_SESSION['password_reset_otp'] ?? null;

        if (!is_array($resetSession) || empty($resetSession['email']) || empty($resetSession['user_id'])) {
            $error = 'No reset OTP session found. Request a new OTP.';
            $showOtpForm = false;
        } else {
            try {
                $otp = generateOtpCode();
                $_SESSION['password_reset_otp'] = [
                    'user_id' => (int) $resetSession['user_id'],
                    'email' => (string) $resetSession['email'],
                    'code' => password_hash($otp, PASSWORD_DEFAULT),
                    'expires_at' => time() + OTP_EXPIRY_SECONDS,
                ];

                $showOtpForm = true;
                $sent = sendOtpEmail((string) $resetSession['email'], $otp);
                $success = $sent
                    ? 'A new OTP has been sent to your email.'
                    : 'A new OTP was generated. Email delivery is not configured, so check storage/logs/otp.log.';
                $devOtp = defined('OTP_DEV_EXPOSE_CODE') && OTP_DEV_EXPOSE_CODE ? $otp : '';
            } catch (Throwable $e) {
                $error = 'Unable to resend OTP right now.';
                $showOtpForm = true;
            }
        }
    } elseif ($action === 'edit_email') {
        unset($_SESSION['password_reset_otp']);
        $showOtpForm = false;
        $success = '';
        $devOtp = '';
    } elseif ($action === 'reset_password') {
        $otpInput = trim((string) ($_POST['otp_code'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $resetSession = $_SESSION['password_reset_otp'] ?? null;

        if (!is_array($resetSession)) {
            $error = 'No reset OTP session found. Request a new OTP.';
            $showOtpForm = false;
        } elseif (!preg_match('/^[0-9]{6}$/', $otpInput)) {
            $error = 'Enter a valid 6-digit OTP.';
            $showOtpForm = true;
        } elseif (time() > (int) $resetSession['expires_at']) {
            unset($_SESSION['password_reset_otp']);
            $error = 'OTP expired. Request a new one.';
            $showOtpForm = false;
        } elseif (!password_verify($otpInput, (string) $resetSession['code'])) {
            $error = 'Incorrect OTP.';
            $showOtpForm = true;
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
            $showOtpForm = true;
        } elseif ($password !== $confirmPassword) {
            $error = 'Password and confirm password do not match.';
            $showOtpForm = true;
        } else {
            try {
                $pdo = getConnection();
                $accountType = (string) ($resetSession['account_type'] ?? 'student');
                $table = $accountType === 'admin' ? 'admin_users' : 'users';
                $update = $pdo->prepare("UPDATE {$table} SET password_hash = :password_hash WHERE id = :id");
                $update->execute([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $resetSession['user_id'],
                ]);

                unset($_SESSION['password_reset_otp']);
                $showOtpForm = false;
                $success = 'Password reset successfully. You can now login.';
            } catch (Throwable $e) {
                $error = 'Unable to reset password right now.';
                $showOtpForm = true;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card auth-card-login">
            <div class="auth-head">
                <h1>Forgot Password</h1>
                <p>Enter your email, generate OTP, then reset your password</p>
            </div>
            <?php if ($error !== ''): ?>
                <p class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <p class="message success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($showOtpForm && is_array($_SESSION['password_reset_otp'] ?? null)): ?>
                <div class="account-box" style="margin-bottom: 1rem;">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars((string) $_SESSION['password_reset_otp']['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>OTP Validity:</strong> 5 minutes</p>
                    <?php if ($devOtp !== ''): ?>
                        <p><strong>Test OTP:</strong> <?php echo htmlspecialchars($devOtp, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
                <form method="post" class="auth-form">
                    <input type="hidden" name="action" value="reset_password">
                    <label>Enter OTP *
                        <input type="text" name="otp_code" required pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP">
                    </label>
                    <label>New Password *
                        <input type="password" name="password" required minlength="6" placeholder="Enter new password">
                    </label>
                    <label>Confirm Password *
                        <input type="password" name="confirm_password" required minlength="6" placeholder="Confirm new password">
                    </label>
                    <button class="btn-primary" type="submit">Verify OTP and Reset Password</button>
                </form>
                <form method="post" class="auth-form">
                    <input type="hidden" name="action" value="resend_otp">
                    <button class="btn-secondary" type="submit">Resend OTP</button>
                </form>
                <form method="post" class="auth-form">
                    <input type="hidden" name="action" value="edit_email">
                    <button class="btn-secondary" type="submit">Edit Email</button>
                </form>
            <?php else: ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="action" value="request_otp">
                    <label>Email Address *
                        <input type="email" name="email" required placeholder="your.email@example.com" value="<?php echo htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <button class="btn-primary" type="submit">Generate OTP</button>
                </form>
            <?php endif; ?>
            <p class="auth-link auth-link-center"><a href="login.php">Back to Login</a></p>
        </section>
    </main>
</body>
</html>
