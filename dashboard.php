<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/platform.php';

requireUserLogin();
$user = getCurrentUser();
$applications = [];
$dashboardNotice = '';
$dashboardNoticeType = '';
$latestApplication = null;
$latestPayments = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_my_application') {
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($applicationId === false) {
            $dashboardNotice = 'Invalid application ID.';
            $dashboardNoticeType = 'error';
        } else {
            try {
                $pdo = getConnection();
                $deleteStmt = $pdo->prepare('DELETE FROM applications WHERE id = :id AND email = :email LIMIT 1');
                $deleteStmt->execute([
                    'id' => $applicationId,
                    'email' => (string) ($user['email'] ?? ''),
                ]);

                if ($deleteStmt->rowCount() > 0) {
                    $dashboardNotice = 'Application deleted successfully.';
                    $dashboardNoticeType = 'success';
                } else {
                    $dashboardNotice = 'Application not found for this account.';
                    $dashboardNoticeType = 'error';
                }
            } catch (Throwable $e) {
                $dashboardNotice = 'Unable to delete application right now.';
                $dashboardNoticeType = 'error';
            }
        }
    }
}

try {
    $pdo = getConnection();
    ensurePaymentTable($pdo);
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE email = :email ORDER BY created_at DESC, id DESC');
    $stmt->execute([
        'email' => (string) ($user['email'] ?? ''),
    ]);
    $applications = $stmt->fetchAll();

    if ($applications !== []) {
        $latestPayments = fetchLatestPaymentsByApplicationIds($pdo, array_column($applications, 'id'));
    }
} catch (Throwable $e) {
    $dashboardNotice = 'We could not load your application history right now.';
}

if ($applications !== []) {
    $latestApplication = $applications[0];
}

$documentCount = 0;
if (is_array($latestApplication)) {
    $documentCount += !empty($latestApplication['marksheet_path']) ? 1 : 0;
    $documentCount += !empty($latestApplication['id_proof_path']) ? 1 : 0;
    $documentCount += !empty($latestApplication['photo_path']) ? 1 : 0;
}

$progressPercent = 25;
if (is_array($latestApplication)) {
    $progressPercent = 40 + (int) round(($documentCount / 3) * 35);
}

$paymentDue = 1000;
$latestPayment = $latestApplication ? ($latestPayments[(int) $latestApplication['id']] ?? null) : null;
$paymentStatus = is_array($latestPayment) ? (string) $latestPayment['status'] : 'not submitted';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application Dashboard | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <main class="student-shell">
        <aside class="student-sidebar">
            <div class="student-brand">Online College Admission System</div>
            <div class="student-profile">
                <div class="student-avatar"><?php echo strtoupper(substr((string) ($user['full_name'] ?? 'S'), 0, 1)); ?></div>
                <h2><?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <nav class="student-nav">
                <a href="#overview">Dashboard</a>
                <a href="#history">Application</a>
                <a href="#history">Documents</a>
                <a href="#payment-card">Payment</a>
                <a href="account.php">Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </aside>

        <section class="student-main">
            <div class="student-topbar">
                <div>
                    <p class="application-eyebrow">Student Dashboard</p>
                    <h1>Welcome back, <?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>!</h1>
                </div>
                <a class="btn-secondary" href="index.php">Back to Home</a>
            </div>

            <?php if ($dashboardNotice !== ''): ?>
                <p class="message <?php echo $dashboardNoticeType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($dashboardNotice, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <section id="overview" class="student-panel">
                <div class="dashboard-metrics">
                    <article class="metric-card metric-card-blue">
                        <p>Application</p>
                        <strong><?php echo is_array($latestApplication) ? 'Under Review' : 'Not Started'; ?></strong>
                        <span><?php echo is_array($latestApplication) ? 'Application #' . (int) $latestApplication['id'] : 'Create your first application'; ?></span>
                    </article>
                    <article class="metric-card metric-card-green">
                        <p>Documents</p>
                        <strong><?php echo $documentCount; ?>/3 Verified</strong>
                        <span><?php echo max(0, 3 - $documentCount); ?> pending</span>
                    </article>
                    <article id="payment-card" class="metric-card metric-card-yellow">
                        <p>Payment</p>
                        <strong><?php echo is_array($latestPayment) ? htmlspecialchars(strtoupper($paymentStatus), ENT_QUOTES, 'UTF-8') : 'PENDING'; ?></strong>
                        <span><?php echo is_array($latestPayment) ? 'Txn: ' . htmlspecialchars((string) $latestPayment['transaction_id'], ENT_QUOTES, 'UTF-8') : 'Due now'; ?></span>
                    </article>
                </div>

                <div class="student-progress">
                    <div class="progress-header">
                        <span>Application Progress</span>
                        <span><?php echo $progressPercent; ?>%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%;"></div>
                    </div>
                </div>

                <div class="student-panel pending-panel">
                    <h2>Pending Actions</h2>
                    <div class="pending-item">
                        <span><?php echo max(0, 3 - $documentCount); ?> upload remaining documents</span>
                        <strong><?php echo max(0, 3 - $documentCount); ?> pending</strong>
                    </div>
                    <div class="pending-item">
                        <span><?php echo is_array($latestPayment) ? 'Payment submitted for verification' : 'Pay application fee'; ?></span>
                        <a class="pending-pay" href="<?php echo is_array($latestPayment) ? 'account.php' : 'apply.php'; ?>"><?php echo is_array($latestPayment) ? 'View payment' : 'Pay now'; ?></a>
                    </div>
                </div>
            </section>

            <section id="history" class="student-panel">
                <h2>Application History</h2>
                <?php if ($applications === []): ?>
                    <p class="empty-state">No submitted applications found yet.</p>
                <?php else: ?>
                    <?php foreach ($applications as $index => $application): ?>
                        <article class="application-card">
                            <div class="application-card-head">
                                <div>
                                    <p class="application-eyebrow"><?php echo $index === 0 ? 'Latest Application' : 'Previous Application'; ?></p>
                                    <h3><?php echo htmlspecialchars((string) $application['course'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                </div>
                                <span class="application-date"><?php echo htmlspecialchars((string) date('d M Y, h:i A', strtotime((string) $application['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="application-details">
                                <div class="detail-row">
                                    <span class="detail-label">Application ID</span>
                                    <span class="detail-value">#<?php echo (int) $application['id']; ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Full Name</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['phone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date of Birth</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) date('d M Y', strtotime((string) $application['dob'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Gender</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['gender'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">State</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['state'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">City</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['city'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Zip Code</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['zip_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Previous Marks</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['previous_marks'], ENT_QUOTES, 'UTF-8'); ?>%</span>
                                </div>
                                <div class="detail-row detail-row-full">
                                    <span class="detail-label">Address</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((string) $application['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="application-documents">
                                <?php if (!empty($application['marksheet_path'])): ?>
                                    <a href="<?php echo htmlspecialchars((string) $application['marksheet_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View Marksheet</a>
                                <?php endif; ?>
                                <?php if (!empty($application['id_proof_path'])): ?>
                                    <a href="<?php echo htmlspecialchars((string) $application['id_proof_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View ID Proof</a>
                                <?php endif; ?>
                                <?php if (!empty($application['photo_path'])): ?>
                                    <a href="<?php echo htmlspecialchars((string) $application['photo_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View Photo</a>
                                <?php endif; ?>
                                <?php $payment = $latestPayments[(int) $application['id']] ?? null; ?>
                                <?php if (is_array($payment)): ?>
                                    <span class="btn-secondary">Payment: <?php echo htmlspecialchars((string) $payment['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="btn-secondary">Txn: <?php echo htmlspecialchars((string) $payment['transaction_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <form method="post" class="inline-form" onsubmit="return confirm('Delete this application from your dashboard?');">
                                    <input type="hidden" name="action" value="delete_my_application">
                                    <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                    <button class="btn-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </section>
    </main>
</body>
</html>
