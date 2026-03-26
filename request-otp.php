<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/utilities.php';
require_once __DIR__ . '/../includes/otp.php';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = validateAdmissionPayload($_POST);
if ($payload['errors'] !== []) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Validation failed',
        'errors' => $payload['errors'],
    ]);
    exit;
}

$documentCheck = validateDocumentUploads($_FILES);
if ($documentCheck['errors'] !== []) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Document validation failed',
        'errors' => $documentCheck['errors'],
    ]);
    exit;
}

$data = $payload['clean'];
$deliveryPreference = validateOtpDeliveryPreference($_POST['otp_delivery'] ?? 'both');
$otp = generateOtpCode();
$expiresAt = time() + OTP_EXPIRY_SECONDS;

$_SESSION['admission_otp'] = [
    'code' => password_hash($otp, PASSWORD_DEFAULT),
    'expires_at' => $expiresAt,
    'last_sent_at' => time(),
    'delivery_preference' => $deliveryPreference,
    'payload' => $data,
    'email' => $data['email'],
    'phone' => $data['phone'],
];

$delivery = dispatchOtpByPreference($deliveryPreference, $data['email'], $data['phone'], $otp);

echo json_encode([
    'ok' => true,
    'message' => $delivery['ok']
        ? 'OTP sent. Check your email/SMS and enter it to complete submission.'
        : 'OTP generated, but delivery provider is not configured. Check storage/logs/otp.log',
    'delivery' => [
        'email' => $delivery['email'],
        'sms' => $delivery['sms'],
    ],
    'retry_after' => OTP_RESEND_COOLDOWN_SECONDS,
    'dev_otp' => (defined('OTP_DEV_EXPOSE_CODE') && OTP_DEV_EXPOSE_CODE) ? $otp : null,
]);
