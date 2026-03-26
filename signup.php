<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';

startUserSession();

if (isUserLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) < 3) {
        $error = 'Name must be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirm password do not match.';
    } else {
        try {
            $pdo = getConnection();
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, created_at)
                     VALUES (:full_name, :email, :password_hash, NOW())'
                );
                $insert->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $id = (int) $pdo->lastInsertId();
                loginUser([
                    'id' => $id,
                    'full_name' => $fullName,
                    'email' => $email,
                ]);

                sendNotificationEmail(
                    $email,
                    'Welcome to ' . APP_NAME,
                    "Hello {$fullName},\nYour account has been created successfully. You can now submit your admission application and track it from your dashboard.",
                    'signup-email'
                );

                header('Location: dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Unable to create account right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-icon" aria-hidden="true">
                <span class="auth-icon-circle"></span>
                <span class="auth-icon-body"></span>
            </div>
            <h1>Create Account</h1>
            <p>Sign up to access your account dashboard.</p>
            <?php if ($error !== ''): ?>
                <p class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <label>Full Name
                    <input type="text" name="full_name" required minlength="3">
                </label>
                <label>Email
                    <input type="email" name="email" required>
                </label>
                <label>Password
                    <div class="password-field">
                        <input type="password" name="password" id="signupPassword" required minlength="6">
                        <button type="button" class="password-toggle" id="toggleSignupPassword" aria-label="Show password" aria-pressed="false">
                            <span class="eye-icon" aria-hidden="true"></span>
                            <span class="password-toggle-text">Show</span>
                        </button>
                    </div>
                </label>
                <label>Confirm Password
                    <div class="password-field">
                        <input type="password" name="confirm_password" id="signupConfirmPassword" required minlength="6">
                        <button type="button" class="password-toggle" id="toggleSignupConfirmPassword" aria-label="Show password" aria-pressed="false">
                            <span class="eye-icon" aria-hidden="true"></span>
                            <span class="password-toggle-text">Show</span>
                        </button>
                    </div>
                </label>
                <button class="btn-primary" type="submit">Sign Up</button>
            </form>
            <p class="auth-link">Already have an account? <a href="login.php">Login</a></p>
            <p class="auth-link"><a href="index.php">Back to Home</a></p>
        </section>
    </main>
    <script>
        (function () {
            const togglePasswordField = function (inputId, buttonId) {
                const passwordInput = document.getElementById(inputId);
                const toggleButton = document.getElementById(buttonId);

                if (!passwordInput || !toggleButton) {
                    return;
                }

                toggleButton.addEventListener('click', function () {
                    const isHidden = passwordInput.type === 'password';
                    const toggleText = toggleButton.querySelector('.password-toggle-text');
                    passwordInput.type = isHidden ? 'text' : 'password';
                    toggleButton.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                    toggleButton.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    toggleButton.classList.toggle('is-visible', isHidden);
                    if (toggleText) {
                        toggleText.textContent = isHidden ? 'Hide' : 'Show';
                    }
                });
            };

            togglePasswordField('signupPassword', 'toggleSignupPassword');
            togglePasswordField('signupConfirmPassword', 'toggleSignupConfirmPassword');
        }());
    </script>
</body>
</html>
