<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platform.php';
require_once __DIR__ . '/../includes/site-data.php';
require_once __DIR__ . '/../includes/utilities.php';
require_once __DIR__ . '/../includes/otp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$otpInput = trim((string) ($_POST['otp_code'] ?? ''));
if (!preg_match('/^[0-9]{6}$/', $otpInput)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Enter a valid 6-digit OTP.']);
    exit;
}

$sessionOtp = $_SESSION['admission_otp'] ?? null;
if (!is_array($sessionOtp)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No OTP session found. Submit form again.']);
    exit;
}

if (time() > (int) $sessionOtp['expires_at']) {
    unset($_SESSION['admission_otp']);
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'OTP expired. Submit form again.']);
    exit;
}

if (!password_verify($otpInput, (string) $sessionOtp['code'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Incorrect OTP.']);
    exit;
}

$data = $sessionOtp['payload'];

$documentCheck = validateDocumentUploads($_FILES);
if ($documentCheck['errors'] !== []) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Please upload all required documents in valid format.',
        'errors' => $documentCheck['errors'],
    ]);
    exit;
}

$storedDocuments = storeDocumentUploads($documentCheck['clean']);
if ($storedDocuments['errors'] !== []) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Unable to save uploaded documents.',
    ]);
    exit;
}

$data = array_merge($data, $storedDocuments['paths']);

try {
    $pdo = getConnection();
    ensureApplicationDraftTable($pdo);
    $pdo->beginTransaction();
    $user = getCurrentUser();

    $deleteStmt = $pdo->prepare('DELETE FROM applications WHERE email = :email');
    $deleteStmt->execute([
        'email' => $data['email'],
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO applications
        (full_name, email, phone, dob, gender, course, address, city, state, zip_code, previous_marks, marksheet_path, id_proof_path, photo_path, created_at)
        VALUES
        (:full_name, :email, :phone, :dob, :gender, :course, :address, :city, :state, :zip_code, :previous_marks, :marksheet_path, :id_proof_path, :photo_path, NOW())'
    );
    $stmt->execute($data);
    $applicationId = (int) $pdo->lastInsertId();

    if (is_array($user)) {
        $draftDeleteStmt = $pdo->prepare('DELETE FROM application_drafts WHERE user_id = :user_id');
        $draftDeleteStmt->execute(['user_id' => (int) $user['id']]);
    }

    $pdo->commit();

    unset($_SESSION['admission_otp']);

    $feeAmount = getCourseFeeAmount((string) $data['course']);
    $subject = 'Application Submitted Successfully';
    $body = "Your application has been submitted successfully.\n"
        . "Application ID: #" . $applicationId . "\n"
        . "Course: " . $data['course'] . "\n"
        . "Yearly Fee: Rs " . number_format((float) $feeAmount, 2) . "\n"
        . "You can now continue to payment from your dashboard.";
    sendNotificationEmail((string) $data['email'], $subject, $body, 'application-email');

    echo json_encode([
        'ok' => true,
        'message' => 'Application submitted successfully. Previous applications for this email were replaced.',
        'application_id' => $applicationId,
        'course' => $data['course'],
        'fee_amount' => $feeAmount,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server error while saving application.',
    ]);
}
