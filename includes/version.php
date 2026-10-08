<?php
/**
 * Sharan Foundation — Application Version Constants
 *
 * Single source of truth for the app version. The same string is also
 * recorded in the `schema_version` table during install.
 */

define('APP_VERSION',         'v1.0.0');
define('APP_VERSION_NAME',    'Consolidated Release');
define('APP_VERSION_DATE',    '2026-06-15');
define('APP_MIN_PHP_VERSION', '7.4.0');

/**
 * Get the currently-installed schema version (latest row in schema_version table).
 * Returns null if the table doesn't exist or is empty.
 */
function get_schema_version(?PDO $pdo = null): ?array {
    if (!$pdo) {
        global $pdo;
        if (!$pdo) return null;
    }
    try {
        $row = $pdo->query("SELECT * FROM schema_version ORDER BY id DESC LIMIT 1")->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get full schema version history (audit trail).
 */
function get_schema_history(?PDO $pdo = null): array {
    if (!$pdo) {
        global $pdo;
        if (!$pdo) return [];
    }
    try {
        return $pdo->query("SELECT * FROM schema_version ORDER BY id DESC")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Check if app code version matches installed schema version.
 * Returns true if matched, false if mismatched (suggests re-run install.php).
 */
function is_schema_up_to_date(?PDO $pdo = null): bool {
    $installed = get_schema_version($pdo);
    if (!$installed) return false;
    return $installed['version'] === APP_VERSION;
}
