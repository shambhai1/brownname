<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platform.php';

$user = getCurrentUser();
if (!is_array($user)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Login required for database draft loading.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getConnection();
    ensureApplicationDraftTable($pdo);
    $stmt = $pdo->prepare('SELECT draft_payload, updated_at FROM application_drafts WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => (int) $user['id']]);
    $draft = $stmt->fetch();

    echo json_encode([
        'ok' => true,
        'draft' => $draft ? json_decode((string) $draft['draft_payload'], true) : null,
        'updated_at' => $draft['updated_at'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to load draft right now.']);
}
