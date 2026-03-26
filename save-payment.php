<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platform.php';
require_once __DIR__ . '/../includes/site-data.php';
require_once __DIR__ . '/../includes/otp.php';

$user = getCurrentUser();
if (!is_array($user)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required before saving payment.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$paymentMethod = trim((string) ($_POST['payment_method'] ?? ''));
$transactionId = trim((string) ($_POST['transaction_id'] ?? ''));

if ($applicationId === false) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid application ID.']);
    exit;
}

if ($paymentMethod === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Select a payment method.']);
    exit;
}

if ($transactionId === '' || mb_strlen($transactionId) < 6) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Enter a valid transaction ID.']);
    exit;
}

$screenshotPath = null;
$screenshot = $_FILES['payment_screenshot'] ?? null;
if (is_array($screenshot) && (int) ($screenshot['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $errorCode = (int) ($screenshot['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Payment screenshot upload failed.']);
        exit;
    }

    $size = (int) ($screenshot['size'] ?? 0);
    if ($size <= 0 || $size > DOCUMENT_MAX_SIZE_BYTES) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Payment screenshot must be under 2 MB.']);
        exit;
    }

    $originalName = (string) ($screenshot['name'] ?? '');
    $tmpPath = (string) ($screenshot['tmp_name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Payment screenshot must be JPG, PNG, or PDF.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../storage/payments';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to create payment storage directory.']);
        exit;
    }

    $fileName = 'payment_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $absolutePath = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpPath, $absolutePath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to save payment screenshot.']);
        exit;
    }

    $screenshotPath = 'storage/payments/' . $fileName;
}

try {
    $pdo = getConnection();
    ensurePaymentTable($pdo);

    $applicationStmt = $pdo->prepare('SELECT id, email, course FROM applications WHERE id = :id LIMIT 1');
    $applicationStmt->execute(['id' => $applicationId]);
    $application = $applicationStmt->fetch();

    if (!$application || strcasecmp((string) $application['email'], (string) $user['email']) !== 0) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'You can only save payment for your own application.']);
        exit;
    }

    $amount = (float) getCourseFeeAmount((string) $application['course']);
    $stmt = $pdo->prepare(
        'INSERT INTO payments (application_id, user_id, payment_method, amount, transaction_id, screenshot_path, status, created_at, updated_at)
         VALUES (:application_id, :user_id, :payment_method, :amount, :transaction_id, :screenshot_path, :status, NOW(), NOW())'
    );
    $stmt->execute([
        'application_id' => (int) $application['id'],
        'user_id' => (int) $user['id'],
        'payment_method' => $paymentMethod,
        'amount' => $amount,
        'transaction_id' => $transactionId,
        'screenshot_path' => $screenshotPath,
        'status' => 'submitted',
    ]);

    $subject = 'Payment Submitted for Verification';
    $body = "We received your payment submission for " . $application['course'] . ".\n"
        . "Transaction ID: " . $transactionId . "\n"
        . "Amount: Rs " . number_format($amount, 2) . "\n"
        . "Status: submitted for admin verification.";
    sendNotificationEmail((string) $application['email'], $subject, $body, 'payment-email');

    echo json_encode([
        'ok' => true,
        'message' => 'Payment details saved successfully. Admin will review your payment.',
        'payment' => [
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'status' => 'submitted',
            'screenshot_path' => $screenshotPath,
        ],
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'This transaction ID already exists.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to save payment right now.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to save payment right now.']);
}
