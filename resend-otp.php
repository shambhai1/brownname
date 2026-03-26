<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/otp.php';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$sessionOtp = $_SESSION['admission_otp'] ?? null;
if (!is_array($sessionOtp)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No OTP session found. Submit form again.']);
    exit;
}

$now = time();
$lastSentAt = (int) ($sessionOtp['last_sent_at'] ?? 0);
$elapsed = $now - $lastSentAt;
$remaining = OTP_RESEND_COOLDOWN_SECONDS - $elapsed;

if ($remaining > 0) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'message' => 'Please wait before resending OTP.',
        'retry_after' => $remaining,
    ]);
    exit;
}

$otp = generateOtpCode();
$sessionOtp['code'] = password_hash($otp, PASSWORD_DEFAULT);
$sessionOtp['expires_at'] = $now + OTP_EXPIRY_SECONDS;
$sessionOtp['last_sent_at'] = $now;

$deliveryPreference = (string) ($sessionOtp['delivery_preference'] ?? 'both');
$email = (string) ($sessionOtp['email'] ?? '');
$phone = (string) ($sessionOtp['phone'] ?? '');

$delivery = dispatchOtpByPreference($deliveryPreference, $email, $phone, $otp);

$_SESSION['admission_otp'] = $sessionOtp;

echo json_encode([
    'ok' => true,
    'message' => $delivery['ok']
        ? 'OTP resent successfully.'
        : 'OTP resent, but provider not configured. Check storage/logs/otp.log',
    'delivery' => [
        'email' => $delivery['email'],
        'sms' => $delivery['sms'],
    ],
    'retry_after' => OTP_RESEND_COOLDOWN_SECONDS,
    'dev_otp' => (defined('OTP_DEV_EXPOSE_CODE') && OTP_DEV_EXPOSE_CODE) ? $otp : null,
]);
