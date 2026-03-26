<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getConnection();
    $stmt = $pdo->query('SELECT 1 AS ok');
    $row = $stmt->fetch();

    echo json_encode([
        'ok' => true,
        'message' => 'Database connected successfully.',
        'payload' => $row,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection failed.',
        'error' => $e->getMessage(),
    ]);
}
?>
