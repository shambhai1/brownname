<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/utilities.php';

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

$data = $payload['clean'];

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO applications
        (full_name, email, phone, dob, gender, course, address, city, state, zip_code, previous_marks, created_at)
        VALUES
        (:full_name, :email, :phone, :dob, :gender, :course, :address, :city, :state, :zip_code, :previous_marks, NOW())'
    );

    $stmt->execute($data);

    echo json_encode([
        'ok' => true,
        'message' => 'Application submitted successfully.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server error while saving application.',
    ]);
}

