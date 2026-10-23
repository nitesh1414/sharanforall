<?php
/**
 * Sharan Foundation — Database Configuration
 * Edit credentials below to match your MySQL setup.
 */

//define('DB_HOST', 'localhost');
//define('DB_NAME', 'u784555160_acts');
//define('DB_USER', 'u784555160_acts');        // XAMPP default
//define('DB_PASS', 'Acts@Foundation$1234');            // XAMPP default (empty)
//define('DB_CHARSET', 'utf8mb4');

define('DB_HOST', 'localhost');
define('DB_NAME', 'u784555160_acts');
define('DB_USER', 'root');        // XAMPP default
define('DB_PASS', '');            // XAMPP default (empty)
define('DB_CHARSET', 'utf8mb4');


// Base URL — change if your folder name differs.
define('BASE_URL', '');
define('ADMIN_URL', BASE_URL . 'admin/');
define('UPLOAD_URL', BASE_URL . 'uploads/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:Arial;padding:2rem;max-width:600px;margin:3rem auto;background:#fee;border-left:4px solid #c00;border-radius:6px"><h3 style="color:#c00">Database Connection Failed</h3><p>' . htmlspecialchars($e->getMessage()) . '</p><p><strong>Quick fix:</strong> Open <code>config/database.php</code> and update DB_HOST, DB_USER, DB_PASS. Also make sure you imported <code>sql/acts_foundation.sql</code> in phpMyAdmin.</p></div>');
}
