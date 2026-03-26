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
    echo json_encode(['ok' => false, 'message' => 'Login required for database draft saving.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);
if (!is_array($payload)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid draft payload.']);
    exit;
}

$action = (string) ($payload['action'] ?? 'save');

try {
    $pdo = getConnection();
    ensureApplicationDraftTable($pdo);

    if ($action === 'clear') {
        $deleteStmt = $pdo->prepare('DELETE FROM application_drafts WHERE user_id = :user_id');
        $deleteStmt->execute(['user_id' => (int) $user['id']]);

        echo json_encode(['ok' => true, 'message' => 'Draft cleared.']);
        exit;
    }

    $draftPayload = $payload['draft'] ?? null;
    if (!is_array($draftPayload)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Draft data is required.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO application_drafts (user_id, draft_payload, updated_at)
         VALUES (:user_id, :draft_payload, NOW())
         ON DUPLICATE KEY UPDATE draft_payload = VALUES(draft_payload), updated_at = NOW()'
    );
    $stmt->execute([
        'user_id' => (int) $user['id'],
        'draft_payload' => json_encode($draftPayload, JSON_UNESCAPED_UNICODE),
    ]);

    echo json_encode(['ok' => true, 'message' => 'Draft saved.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to save draft right now.']);
}
