<?php
/**
 * Sharan Foundation — Library Bootstrap
 *
 * Loads PHPMailer + FPDF using the FIRST available source, in this priority order:
 *
 *   1. Composer's autoloader (vendor/autoload.php) if present
 *      → use this in production: `composer install`
 *
 *   2. Bundled copies in includes/PHPMailer/ and includes/FPDF/
 *      → zero-dependency default, ships with the project
 *
 * This means hosts with Composer get fully-maintained library versions,
 * while shared-hosting users without shell access still get a working site.
 */

// 1. Try Composer first
$composer_autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composer_autoload)) {
    require_once $composer_autoload;
    if (!defined('ACTS_LIBS_SOURCE')) define('ACTS_LIBS_SOURCE', 'composer');
}

// 2. Fall back to bundled libraries (PHPMailer)
// We always register the autoloader — Composer's takes precedence because it's
// registered first, this only fires for classes Composer can't resolve.
spl_autoload_register(function ($class) {
    if (strpos($class, 'PHPMailer\\PHPMailer\\') === 0) {
        $relative = substr($class, strlen('PHPMailer\\PHPMailer\\'));
        $file = __DIR__ . '/PHPMailer/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
            if (!defined('ACTS_LIBS_SOURCE')) define('ACTS_LIBS_SOURCE', 'bundled');
        }
    }
});

// 3. FPDF doesn't use namespaces — load on demand via helper
if (!function_exists('acts_load_fpdf')) {
    function acts_load_fpdf(): void {
        if (class_exists('FPDF', false)) return;  // already loaded
        $bundled = __DIR__ . '/FPDF/fpdf.php';
        if (is_file($bundled)) {
            require_once $bundled;
            if (!defined('ACTS_LIBS_SOURCE')) define('ACTS_LIBS_SOURCE', 'bundled');
        }
    }
}

// Default if neither path triggered
if (!defined('ACTS_LIBS_SOURCE')) define('ACTS_LIBS_SOURCE', 'unknown');

/**
 * Report what library sources are active (used by Settings page + Security Check).
 */
function acts_lib_info(): array {
    $composer_present = is_file(__DIR__ . '/../vendor/autoload.php');
    $bundled_phpmailer = is_file(__DIR__ . '/PHPMailer/PHPMailer.php');
    $bundled_fpdf      = is_file(__DIR__ . '/FPDF/fpdf.php');
    return [
        'composer'         => $composer_present,
        'bundled_phpmailer'=> $bundled_phpmailer,
        'bundled_fpdf'     => $bundled_fpdf,
        'source'           => defined('ACTS_LIBS_SOURCE') ? ACTS_LIBS_SOURCE : 'unknown',
    ];
}
