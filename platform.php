<?php
declare(strict_types=1);

function ensurePaymentTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED DEFAULT NULL,
            payment_method VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            transaction_id VARCHAR(120) NOT NULL,
            screenshot_path VARCHAR(255) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "submitted",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_payments_transaction_id (transaction_id),
            KEY idx_payments_application_id (application_id),
            KEY idx_payments_user_id (user_id),
            KEY idx_payments_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensureApplicationDraftTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS application_drafts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL UNIQUE,
            draft_payload JSON NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_application_drafts_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function fetchLatestPaymentsByApplicationIds(PDO $pdo, array $applicationIds): array
{
    $applicationIds = array_values(array_filter(array_map('intval', $applicationIds), static fn (int $id): bool => $id > 0));
    if ($applicationIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.*
         FROM payments p
         INNER JOIN (
             SELECT application_id, MAX(id) AS latest_id
             FROM payments
             WHERE application_id IN ($placeholders)
             GROUP BY application_id
         ) latest ON latest.latest_id = p.id"
    );
    $stmt->execute($applicationIds);

    $rows = $stmt->fetchAll();
    $mapped = [];
    foreach ($rows as $row) {
        $mapped[(int) $row['application_id']] = $row;
    }

    return $mapped;
}

function fetchLatestPaymentForApplication(PDO $pdo, int $applicationId): ?array
{
    ensurePaymentTable($pdo);
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE application_id = :application_id ORDER BY id DESC LIMIT 1');
    $stmt->execute(['application_id' => $applicationId]);
    $payment = $stmt->fetch();

    return $payment ?: null;
}
