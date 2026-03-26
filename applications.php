<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platform.php';

requireAdminLogin();
$admin = getCurrentAdmin();

$applications = [];
$paymentsByApplicationId = [];
$dbError = null;
$noticeType = null;
$noticeMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'delete_application') {
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($applicationId === false) {
            $noticeType = 'error';
            $noticeMessage = 'Invalid application ID.';
        } else {
            try {
                $pdo = getConnection();
                $deleteStmt = $pdo->prepare('DELETE FROM applications WHERE id = :id LIMIT 1');
                $deleteStmt->execute(['id' => $applicationId]);

                $noticeType = 'success';
                $noticeMessage = ($deleteStmt->rowCount() > 0)
                    ? 'Application deleted successfully.'
                    : 'Record not found or already deleted.';
            } catch (Throwable $e) {
                $noticeType = 'error';
                $noticeMessage = 'Could not delete application due to a server error.';
            }
        }
    }
}

try {
    $pdo = getConnection();
    ensurePaymentTable($pdo);
    $stmt = $pdo->query('SELECT * FROM applications ORDER BY id DESC LIMIT 200');
    $applications = $stmt->fetchAll();
    $paymentsByApplicationId = fetchLatestPaymentsByApplicationIds($pdo, array_column($applications, 'id'));
} catch (Throwable $e) {
    $dbError = 'Could not connect to database. Import schema and check MySQL credentials in config/config.php';
}

$totalApplications = count($applications);
$documentVerifiedCount = 0;
$pendingDocumentsCount = 0;
$averageMarks = 0.0;

if ($applications !== []) {
    $totalMarks = 0.0;

    foreach ($applications as $application) {
        $documentCount = 0;
        $documentCount += !empty($application['marksheet_path']) ? 1 : 0;
        $documentCount += !empty($application['id_proof_path']) ? 1 : 0;
        $documentCount += !empty($application['photo_path']) ? 1 : 0;

        if ($documentCount === 3) {
            $documentVerifiedCount++;
        } else {
            $pendingDocumentsCount++;
        }

        $totalMarks += (float) ($application['previous_marks'] ?? 0);
    }

    $averageMarks = round($totalMarks / $totalApplications, 2);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Applications | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .admin-sidebar .student-avatar { background: linear-gradient(135deg, #eef4ff, #dce9ff); }
        .admin-sidebar .student-nav a.is-active { background: #eef4ff; color: #173f85; }
        .admin-content { display: grid; gap: 1rem; }
        .admin-section-title { margin: 0 0 1rem; font-size: 1.3rem; }
        .table-wrap { overflow-x: auto; background: #fff; border-radius: 12px; border: 1px solid #dce6f6; padding: 0.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        th, td { border-bottom: 1px solid #edf2fc; text-align: left; padding: 0.65rem; white-space: nowrap; }
        th { background: #f6f9ff; }
        .alert { color: #b91c1c; font-weight: 700; }
        .inline-form { margin: 0; }
        .row-actions { display: flex; gap: 0.45rem; align-items: center; }
        .btn-edit { display: inline-flex; align-items: center; justify-content: center; border: 1px solid #2563eb; background: #1d4ed8; color: #fff; text-decoration: none; border-radius: 8px; padding: 0.45rem 0.7rem; font: inherit; font-size: 0.84rem; font-weight: 700; cursor: pointer; }
        .btn-edit:hover { background: #1e40af; }
        .btn-danger { border: 1px solid #ef4444; background: #dc2626; color: #fff; border-radius: 8px; padding: 0.45rem 0.7rem; font: inherit; font-size: 0.84rem; font-weight: 700; cursor: pointer; }
        .btn-danger:hover { background: #b91c1c; }
        .admin-quick-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 1rem; }
        .admin-actions-list { display: grid; gap: 0.7rem; }
        .admin-action-item { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.8rem 0.9rem; border-radius: 12px; background: #f7faff; border: 1px solid #dce6f6; }
        .admin-action-item strong { color: #173f85; }
        .admin-chip { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 0.4rem 0.7rem; background: #eef4ff; color: #173f85; font-weight: 800; }
        .payment-status-chip { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 0.35rem 0.65rem; background: #eef4ff; color: #173f85; font-weight: 800; font-size: 0.8rem; }
        @media (max-width: 820px) {
            .admin-quick-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="student-shell">
        <aside class="student-sidebar admin-sidebar">
            <div class="student-brand">Online College Admission System</div>
            <div class="student-profile">
                <div class="student-avatar">A</div>
                <h2>Administrator</h2>
                <p><?php echo htmlspecialchars((string) ($admin['email'] ?? 'admin'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <nav class="student-nav">
                <a class="is-active" href="#overview">Dashboard</a>
                <a href="#applications-table">Applications</a>
                <a href="login.php">Create Admin</a>
                <a href="../index.php">Home</a>
                <a href="logout.php">Logout</a>
            </nav>
        </aside>

        <section class="student-main admin-content">
            <div class="student-topbar">
                <div>
                    <p class="application-eyebrow">Administrator Dashboard</p>
                    <h1>Manage Student Applications</h1>
                </div>
                <div class="dashboard-actions">
                    <a class="btn-secondary" href="login.php">Admin Accounts</a>
                    <a class="btn-primary" href="../index.php">Back to Home</a>
                </div>
            </div>

            <?php if ($dbError !== null): ?>
                <p class="message error"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php else: ?>
                <?php if ($noticeType === 'success' && $noticeMessage !== ''): ?>
                    <p class="message success"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif ($noticeType === 'error' && $noticeMessage !== ''): ?>
                    <p class="message error"><?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <section id="overview" class="student-panel">
                    <div class="dashboard-metrics">
                        <article class="metric-card metric-card-blue">
                            <p>Total Applications</p>
                            <strong><?php echo $totalApplications; ?></strong>
                            <span>Last 200 records loaded</span>
                        </article>
                        <article class="metric-card metric-card-green">
                            <p>Documents Complete</p>
                            <strong><?php echo $documentVerifiedCount; ?></strong>
                            <span><?php echo $pendingDocumentsCount; ?> need review</span>
                        </article>
                        <article class="metric-card metric-card-yellow">
                            <p>Average Marks</p>
                            <strong><?php echo number_format($averageMarks, 2); ?>%</strong>
                            <span>Across loaded applications</span>
                        </article>
                    </div>

                    <div class="admin-quick-grid">
                        <div class="student-panel pending-panel">
                            <h2>Admin Actions</h2>
                            <div class="admin-actions-list">
                                <div class="admin-action-item">
                                    <span>Update any student application from A to Z</span>
                                    <strong>Edit records</strong>
                                </div>
                                <div class="admin-action-item">
                                    <span>Delete invalid or duplicate student records</span>
                                    <strong>Clean data</strong>
                                </div>
                                <div class="admin-action-item">
                                    <span>Open uploaded marksheet, ID proof, and photo</span>
                                    <strong>Review docs</strong>
                                </div>
                            </div>
                        </div>
                        <div class="student-panel">
                            <h2 class="admin-section-title">Quick Summary</h2>
                            <p class="page-intro">Use the application table below to open full student details, edit records, or delete entries from the system.</p>
                            <p><span class="admin-chip"><?php echo $totalApplications; ?> active rows</span></p>
                        </div>
                    </div>
                </section>

                <section id="applications-table" class="student-panel">
                    <h2 class="admin-section-title">Student Application Records</h2>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>DOB</th>
                                    <th>Gender</th>
                                    <th>Course</th>
                                    <th>City</th>
                                    <th>State</th>
                                    <th>Marks</th>
                                    <th>Payment</th>
                                    <th>Txn ID</th>
                                    <th>Marksheet</th>
                                    <th>ID Proof</th>
                                    <th>Photo</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $row): ?>
                                    <tr>
                                        <td><?php echo (int) $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['dob'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['course'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['state'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['previous_marks'], ENT_QUOTES, 'UTF-8'); ?>%</td>
                                        <?php $payment = $paymentsByApplicationId[(int) $row['id']] ?? null; ?>
                                        <td>
                                            <?php if (is_array($payment)): ?>
                                                <span class="payment-status-chip"><?php echo htmlspecialchars((string) $payment['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo is_array($payment) ? htmlspecialchars((string) $payment['transaction_id'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                        <td>
                                            <?php if (!empty($row['marksheet_path'])): ?>
                                                <a href="../<?php echo htmlspecialchars($row['marksheet_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View</a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['id_proof_path'])): ?>
                                                <a href="../<?php echo htmlspecialchars($row['id_proof_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View</a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['photo_path'])): ?>
                                                <a href="../<?php echo htmlspecialchars($row['photo_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">View</a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="row-actions">
                                                <a class="btn-edit" href="edit-application.php?id=<?php echo (int) $row['id']; ?>">Edit</a>
                                                <form class="inline-form" method="post" onsubmit="return confirm('Delete this application permanently?');">
                                                    <input type="hidden" name="action" value="delete_application">
                                                    <input type="hidden" name="application_id" value="<?php echo (int) $row['id']; ?>">
                                                    <button class="btn-danger" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
