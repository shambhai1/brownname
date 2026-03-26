<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/platform.php';

requireUserLogin();
$user = getCurrentUser();
$accountNotice = '';
$latestApplication = null;
$accountNoticeType = '';
$latestPayment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_email') {
        $newEmail = trim((string) ($_POST['email'] ?? ''));
        $currentEmail = (string) ($user['email'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $accountNotice = 'Enter a valid email address.';
            $accountNoticeType = 'error';
        } elseif (strcasecmp($newEmail, $currentEmail) === 0) {
            $accountNotice = 'This is already your current email address.';
            $accountNoticeType = 'error';
        } else {
            try {
                $pdo = getConnection();
                $pdo->beginTransaction();

                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
                $checkStmt->execute([
                    'email' => $newEmail,
                    'id' => (int) ($user['id'] ?? 0),
                ]);

                if ($checkStmt->fetch()) {
                    $pdo->rollBack();
                    $accountNotice = 'Another account already uses that email address.';
                    $accountNoticeType = 'error';
                } else {
                    $userStmt = $pdo->prepare('UPDATE users SET email = :new_email WHERE id = :id');
                    $userStmt->execute([
                        'new_email' => $newEmail,
                        'id' => (int) ($user['id'] ?? 0),
                    ]);

                    $applicationStmt = $pdo->prepare('UPDATE applications SET email = :new_email WHERE email = :old_email');
                    $applicationStmt->execute([
                        'new_email' => $newEmail,
                        'old_email' => $currentEmail,
                    ]);

                    $pdo->commit();

                    $user['email'] = $newEmail;
                    updateCurrentUserSession($user);
                    $accountNotice = 'Account email updated successfully.';
                    $accountNoticeType = 'success';
                }
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $accountNotice = 'Unable to update email right now.';
                $accountNoticeType = 'error';
            }
        }
    } elseif ($action === 'update_profile') {
        $newName = trim((string) ($_POST['full_name'] ?? ''));

        if ($newName === '' || mb_strlen($newName) < 3) {
            $accountNotice = 'Name must be at least 3 characters.';
            $accountNoticeType = 'error';
        } else {
            try {
                $pdo = getConnection();
                $updateStmt = $pdo->prepare('UPDATE users SET full_name = :full_name WHERE id = :id');
                $updateStmt->execute([
                    'full_name' => $newName,
                    'id' => (int) ($user['id'] ?? 0),
                ]);

                $user['full_name'] = $newName;
                updateCurrentUserSession($user);
                $accountNotice = 'Profile details updated successfully.';
                $accountNoticeType = 'success';
            } catch (Throwable $e) {
                $accountNotice = 'Unable to update profile right now.';
                $accountNoticeType = 'error';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $accountNotice = 'Fill in all password fields.';
            $accountNoticeType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $accountNotice = 'New password must be at least 6 characters.';
            $accountNoticeType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $accountNotice = 'New password and confirm password do not match.';
            $accountNoticeType = 'error';
        } else {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => (int) ($user['id'] ?? 0)]);
                $currentUserRow = $stmt->fetch();

                if (!$currentUserRow || !password_verify($currentPassword, (string) $currentUserRow['password_hash'])) {
                    $accountNotice = 'Current password is incorrect.';
                    $accountNoticeType = 'error';
                } else {
                    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                    $updateStmt->execute([
                        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'id' => (int) ($user['id'] ?? 0),
                    ]);

                    $accountNotice = 'Password changed successfully.';
                    $accountNoticeType = 'success';
                }
            } catch (Throwable $e) {
                $accountNotice = 'Unable to change password right now.';
                $accountNoticeType = 'error';
            }
        }
    }
}

try {
    $pdo = getConnection();
    ensurePaymentTable($pdo);
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE email = :email ORDER BY created_at DESC, id DESC LIMIT 1');
    $stmt->execute([
        'email' => (string) ($user['email'] ?? ''),
    ]);
    $latestApplication = $stmt->fetch();

    if ($latestApplication) {
        $latestPayment = fetchLatestPaymentForApplication($pdo, (int) $latestApplication['id']);
    }
} catch (Throwable $e) {
    $accountNotice = 'We could not load your submitted applications right now.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Account | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <main class="account-shell">
        <section class="account-stage">
            <div class="account-header">
                <div>
                    <p class="application-eyebrow">Account Center</p>
                    <h1>My Account</h1>
                    <p>Welcome, <?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="dashboard-actions">
                    <a class="btn-secondary" href="dashboard.php">Student Dashboard</a>
                    <a class="btn-secondary" href="index.php">Back to Home</a>
                    <a class="btn-secondary" href="logout.php">Logout</a>
                </div>
            </div>

            <div class="account-main-grid">
                <div class="account-column">
                    <div class="account-box account-summary account-box-rect">
                        <h2>Profile Details</h2>
                        <div class="detail-row">
                            <span class="detail-label">Name</span>
                            <span class="detail-value"><?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">User ID</span>
                            <span class="detail-value">#<?php echo (int) ($user['id'] ?? 0); ?></span>
                        </div>
                    </div>

                    <div class="account-box account-box-rect">
                        <h2>Edit Profile</h2>
                        <?php if ($accountNotice !== ''): ?>
                            <p class="message <?php echo $accountNoticeType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($accountNotice, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <form method="post" class="auth-form">
                            <input type="hidden" name="action" value="update_profile">
                            <label>Full Name
                                <input type="text" name="full_name" required minlength="3" value="<?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                            <button class="btn-primary" type="submit">Update Profile</button>
                        </form>
                    </div>

                    <div class="account-box account-box-rect">
                        <h2>Edit Account Email</h2>
                        <form method="post" class="auth-form">
                            <input type="hidden" name="action" value="update_email">
                            <label>New Email Address
                                <input type="email" name="email" required value="<?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                            <button class="btn-primary" type="submit">Update Email</button>
                        </form>
                    </div>

                    <div class="account-box account-box-rect">
                        <h2>Change Password</h2>
                        <form method="post" class="auth-form">
                            <input type="hidden" name="action" value="change_password">
                            <label>Current Password
                                <input type="password" name="current_password" required minlength="6">
                            </label>
                            <label>New Password
                                <input type="password" name="new_password" required minlength="6">
                            </label>
                            <label>Confirm New Password
                                <input type="password" name="confirm_password" required minlength="6">
                            </label>
                            <button class="btn-primary" type="submit">Change Password</button>
                        </form>
                    </div>
                </div>

                <div class="account-column account-column-wide">
                    <div class="account-box account-applications account-box-rect">
                        <h2>Your Submitted Application</h2>
                        <?php if ($accountNotice !== '' && $accountNoticeType === 'error' && $latestApplication === false): ?>
                            <p class="message error"><?php echo htmlspecialchars($accountNotice, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php elseif (!$latestApplication): ?>
                            <p class="empty-state">No application found for this account email yet.</p>
                        <?php else: ?>
                            <article class="application-card application-card-rect">
                                <div class="application-card-head">
                                    <div>
                                        <p class="application-eyebrow">Application #<?php echo (int) $latestApplication['id']; ?></p>
                                        <h3><?php echo htmlspecialchars((string) $latestApplication['course'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    </div>
                                    <span class="application-date"><?php echo htmlspecialchars((string) date('d M Y, h:i A', strtotime((string) $latestApplication['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="application-details application-details-wide">
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">Full Name</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">Email</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">Phone</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['phone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">Date of Birth</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) date('d M Y', strtotime((string) $latestApplication['dob'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">Gender</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['gender'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">State</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['state'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">City</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['city'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">Zip Code</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['zip_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="detail-row detail-row-rect">
                                        <span class="detail-label">Previous Marks</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['previous_marks'], ENT_QUOTES, 'UTF-8'); ?>%</span>
                                    </div>
                                    <div class="detail-row detail-row-full detail-row-rect">
                                        <span class="detail-label">Address</span>
                                        <span class="detail-value"><?php echo htmlspecialchars((string) $latestApplication['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <div class="application-documents">
                                    <?php if (!empty($latestApplication['marksheet_path'])): ?>
                                        <a href="<?php echo htmlspecialchars((string) $latestApplication['marksheet_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View Marksheet</a>
                                    <?php endif; ?>
                                    <?php if (!empty($latestApplication['id_proof_path'])): ?>
                                        <a href="<?php echo htmlspecialchars((string) $latestApplication['id_proof_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View ID Proof</a>
                                    <?php endif; ?>
                                    <?php if (!empty($latestApplication['photo_path'])): ?>
                                        <a href="<?php echo htmlspecialchars((string) $latestApplication['photo_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View Photo</a>
                                    <?php endif; ?>
                                </div>
                                <?php if ($latestPayment): ?>
                                    <div class="application-details" style="margin-top:1rem;">
                                        <div class="detail-row detail-row-rect">
                                            <span class="detail-label">Payment Status</span>
                                            <span class="detail-value"><?php echo htmlspecialchars((string) $latestPayment['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="detail-row detail-row-rect">
                                            <span class="detail-label">Payment Method</span>
                                            <span class="detail-value"><?php echo htmlspecialchars((string) $latestPayment['payment_method'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="detail-row detail-row-rect">
                                            <span class="detail-label">Transaction ID</span>
                                            <span class="detail-value"><?php echo htmlspecialchars((string) $latestPayment['transaction_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
