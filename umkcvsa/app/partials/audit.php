<?php
// =====================================================================
// UMKC VSA - Audit Log helper
// Provides log_audit() to record officer/admin actions.
// The app_audit_log table is created automatically on first use.
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function audit_ensure_table(): void {
    static $done = false;
    if ($done) { return; }
    db()->exec(
        'CREATE TABLE IF NOT EXISTS app_audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            user_email VARCHAR(190) NULL,
            action VARCHAR(60) NOT NULL,
            entity VARCHAR(60) NOT NULL,
            details VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (created_at),
            INDEX (entity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

function log_audit(string $action, string $entity, string $details = ''): void {
    try {
        audit_ensure_table();
        $u = current_user();
        $uid   = $u['id']    ?? null;
        $email = $u['email'] ?? null;
        $stmt = db()->prepare(
            'INSERT INTO app_audit_log (user_id, user_email, action, entity, details)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uid !== null ? (int)$uid : null,
            $email,
            mb_substr($action, 0, 60),
            mb_substr($entity, 0, 60),
            mb_substr($details, 0, 500),
        ]);
    } catch (Throwable $e) {
        // Logging must never break the primary action. Swallow errors.
    }
}