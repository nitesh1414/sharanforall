<?php
/**
 * Sharan Foundation — Diagnostic Tool
 *
 * Visit /admin/diagnose.php to see exactly what's wrong when a page returns 500.
 * It shows: PHP errors, missing tables, missing columns, missing functions, file perms.
 *
 * SAFE — read-only, doesn't modify anything.
 * DELETE this file after debugging.
 */

// Show ALL errors regardless of php.ini
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
admin_check();   // must be logged-in admin

echo '<!DOCTYPE html><html><head><title>Diagnose | Sharan Foundation</title>';
echo '<style>body{font-family:Consolas,monospace;background:#0d2940;color:#e8ecef;padding:2rem;max-width:1100px;margin:0 auto;line-height:1.6}';
echo 'h1{color:#f4a261;border-bottom:2px solid #f4a261;padding-bottom:.5rem}';
echo 'h2{color:#60a5fa;margin-top:2rem;padding-top:1rem;border-top:1px solid #2a4060}';
echo '.ok{color:#10b981}.err{color:#ef4444}.warn{color:#f59e0b}.dim{color:#94a3b8}';
echo 'table{width:100%;border-collapse:collapse;margin:.6rem 0}';
echo 'td,th{padding:.5rem .8rem;border-bottom:1px solid #2a4060;text-align:left;vertical-align:top}';
echo 'th{background:#1e3a5f}';
echo 'pre{background:#000;padding:1rem;border-radius:6px;overflow:auto;font-size:.85rem}';
echo 'code{background:#1e3a5f;padding:.1rem .4rem;border-radius:3px}';
echo '.box{background:#152a44;padding:1.2rem;border-radius:8px;border-left:4px solid #60a5fa;margin:.7rem 0}';
echo '.box.err{border-color:#ef4444}.box.ok{border-color:#10b981}.box.warn{border-color:#f59e0b}';
echo 'a{color:#60a5fa}</style></head><body>';
echo '<h1>🔧 Sharan Foundation — Diagnostic Tool</h1>';
echo '<p class="dim">Run at: ' . date('Y-m-d H:i:s') . ' · PHP ' . PHP_VERSION . ' · Server: ' . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '?') . '</p>';

// ============================================================
function check($label, $ok, $detail = '') {
    $cls = $ok === true ? 'ok' : ($ok === false ? 'err' : 'warn');
    $ico = $ok === true ? '✓' : ($ok === false ? '✗' : '⚠');
    echo "<div><span class='$cls'>$ico</span> $label";
    if ($detail) echo " <span class='dim'>— $detail</span>";
    echo "</div>";
}
// ============================================================

echo '<h2>1. PHP Environment</h2>';
echo '<table>';
echo '<tr><th>PHP Version</th><td>' . PHP_VERSION . ' ' . (version_compare(PHP_VERSION, '7.4.0', '>=') ? '<span class="ok">✓ OK</span>' : '<span class="err">✗ TOO OLD (need 7.4+)</span>') . '</td></tr>';
echo '<tr><th>PDO extension</th><td>' . (extension_loaded('pdo_mysql') ? '<span class="ok">✓ loaded</span>' : '<span class="err">✗ missing</span>') . '</td></tr>';
echo '<tr><th>cURL</th><td>' . (function_exists('curl_init') ? '<span class="ok">✓ loaded</span>' : '<span class="err">✗ missing</span>') . '</td></tr>';
echo '<tr><th>OpenSSL</th><td>' . (extension_loaded('openssl') ? '<span class="ok">✓ loaded</span>' : '<span class="err">✗ missing</span>') . '</td></tr>';
echo '<tr><th>fileinfo</th><td>' . (extension_loaded('fileinfo') ? '<span class="ok">✓ loaded</span>' : '<span class="warn">⚠ not loaded (video MIME check disabled)</span>') . '</td></tr>';
echo '<tr><th>upload_max_filesize</th><td>' . ini_get('upload_max_filesize') . '</td></tr>';
echo '<tr><th>post_max_size</th><td>' . ini_get('post_max_size') . '</td></tr>';
echo '<tr><th>memory_limit</th><td>' . ini_get('memory_limit') . '</td></tr>';
echo '<tr><th>display_errors</th><td>' . (ini_get('display_errors') ? '<span class="warn">⚠ ON</span>' : '<span class="ok">✓ OFF</span>') . '</td></tr>';
echo '</table>';

// ============================================================
echo '<h2>2. Required Functions</h2>';
$needed_fns = ['upload_image','upload_video','csrf_check','csrf_field','csrf_token','flash_set','redirect','e','t','tr_field','slugify','time_ago','get_setting'];
echo '<table>';
foreach ($needed_fns as $fn) {
    $exists = function_exists($fn);
    echo "<tr><th>$fn()</th><td>" . ($exists ? "<span class='ok'>✓ exists</span>" : "<span class='err'>✗ MISSING — old includes/functions.php on server</span>") . "</td></tr>";
}
echo '</table>';

// ============================================================
echo '<h2>3. Database Tables</h2>';
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $needed = [
        'schema_version','admins','settings','programs','projects','blog_posts','gallery',
        'team_members','testimonials','volunteers','partners','contacts','subscribers',
        'donations','fundraisers','fundraiser_contributions',
        'recurring_donations','recurring_charges','cron_log',
        'webhook_events','hero_slides',
        'milestones','mission_phases','vision_capacity','program_courses',
        'marquees','site_media',
    ];
    $missing = array_diff($needed, $tables);
    echo '<table>';
    foreach ($needed as $t) {
        $present = in_array($t, $tables);
        echo "<tr><th>$t</th><td>" . ($present ? "<span class='ok'>✓ exists</span>" : "<span class='err'>✗ MISSING</span>") . "</td></tr>";
    }
    echo '</table>';
    if ($missing) {
        echo '<div class="box err"><strong>⚠ ' . count($missing) . ' table(s) missing.</strong> Run <a href="' . BASE_URL . 'install.php">install.php</a> to create them.</div>';
    }
} catch (Throwable $e) {
    echo '<div class="box err">DB error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// ============================================================
echo '<h2>4. hero_slides Schema (the page that crashed)</h2>';
try {
    $cols = $pdo->query("SHOW COLUMNS FROM hero_slides")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($cols, 'Field');
    $needed_cols = ['id','title','title_hi','subtitle','description','image','cta_text','cta_link','cta_text_2','cta_link_2','overlay_color','text_position','badge_text','display_order','status','media_type','video_file','video_url','poster_image'];
    echo '<table><tr><th>Column</th><th>Status</th><th>Type</th></tr>';
    $col_meta = [];
    foreach ($cols as $c) $col_meta[$c['Field']] = $c;
    foreach ($needed_cols as $n) {
        if (isset($col_meta[$n])) {
            echo "<tr><th>$n</th><td><span class='ok'>✓ present</span></td><td class='dim'>" . htmlspecialchars($col_meta[$n]['Type']) . "</td></tr>";
        } else {
            echo "<tr><th>$n</th><td><span class='err'>✗ MISSING</span></td><td class='dim'>—</td></tr>";
        }
    }
    echo '</table>';
    $missing_cols = array_diff($needed_cols, $col_names);
    if ($missing_cols) {
        echo '<div class="box warn"><strong>Missing columns:</strong> ' . implode(', ', $missing_cols) . '<br>The newer <code>hero.php</code> auto-adds these on first visit, OR re-run <a href="' . BASE_URL . 'install.php">install.php</a>.</div>';
    }
    $row_count = (int)$pdo->query("SELECT COUNT(*) FROM hero_slides")->fetchColumn();
    echo "<p>📊 Total hero slides in DB: <strong>$row_count</strong></p>";
} catch (Throwable $e) {
    echo '<div class="box err">hero_slides table issue: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// ============================================================
echo '<h2>5. File System</h2>';
$paths = [
    'config/database.php'                  => 'file',
    'includes/functions.php'               => 'file',
    'includes/bootstrap.php'               => 'file',
    'includes/PHPMailer/PHPMailer.php'     => 'file',
    'includes/FPDF/fpdf.php'               => 'file',
    'admin/hero.php'                       => 'file',
    'admin/includes/header.php'            => 'file',
    'uploads/'                             => 'dir-writable',
    'uploads/hero/'                        => 'dir-writable',
    'uploads/hero/videos/'                 => 'dir-writable',
    'logs/'                                => 'dir-writable',
];
echo '<table>';
foreach ($paths as $p => $kind) {
    $full = __DIR__ . '/../' . $p;
    if ($kind === 'file') {
        $ok = is_file($full);
        echo "<tr><th>$p</th><td>" . ($ok ? "<span class='ok'>✓ exists</span>" : "<span class='err'>✗ MISSING</span>") . "</td></tr>";
    } else {
        $exists = is_dir($full);
        $writable = $exists && is_writable($full);
        if (!$exists) echo "<tr><th>$p</th><td><span class='err'>✗ directory missing</span></td></tr>";
        elseif (!$writable) echo "<tr><th>$p</th><td><span class='err'>✗ NOT writable</span> (chmod 775)</td></tr>";
        else echo "<tr><th>$p</th><td><span class='ok'>✓ writable</span></td></tr>";
    }
}
echo '</table>';

// ============================================================
echo '<h2>6. Try Loading hero.php — Catch the Real Error</h2>';
ob_start();
try {
    // Simulate what hero.php does
    $action = 'list';
    $rows = $pdo->query("SELECT * FROM hero_slides ORDER BY display_order, id")->fetchAll();
    echo '<div class="box ok">✓ Hero list query executed successfully. Found ' . count($rows) . ' rows.</div>';
} catch (Throwable $e) {
    echo '<div class="box err"><strong>The exact error from hero.php is:</strong><br><pre>' . htmlspecialchars($e->getMessage()) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre></div>';
}
echo ob_get_clean();

// ============================================================
echo '<h2>7. Recent PHP Error Log (last 30 lines)</h2>';
$log_paths = [
    ini_get('error_log'),
    '/var/log/php/error.log',
    '/var/log/apache2/error.log',
    __DIR__ . '/../logs/php_errors.log',
];
$found_log = null;
foreach ($log_paths as $lp) {
    if ($lp && is_file($lp) && is_readable($lp)) { $found_log = $lp; break; }
}
if ($found_log) {
    echo '<p class="dim">Reading: <code>' . htmlspecialchars($found_log) . '</code></p>';
    $lines = @file($found_log);
    if ($lines) {
        $tail = array_slice($lines, -30);
        echo '<pre>' . htmlspecialchars(implode('', $tail)) . '</pre>';
    } else {
        echo '<p class="dim">Log file is empty or unreadable.</p>';
    }
} else {
    echo '<p class="dim">Could not locate PHP error log. Ask your host or check cPanel → Errors.</p>';
}

// ============================================================
echo '<h2>8. Next Steps</h2>';
echo '<div class="box ok">';
echo '<ol>';
echo '<li>If tables/columns are missing → visit <a href="' . BASE_URL . 'install.php">install.php</a> to set them up.</li>';
echo '<li>If functions are missing → re-upload <code>includes/functions.php</code> from your project to the server.</li>';
echo '<li>If folders are not writable → run <code>chmod -R 775 uploads/ logs/</code> via cPanel File Manager.</li>';
echo '<li>If you see a specific SQL error above (section 6) → fix that column/table.</li>';
echo '<li><strong>Delete this file</strong> when done: <code>admin/diagnose.php</code></li>';
echo '</ol>';
echo '</div>';

echo '<p class="dim" style="margin-top:2rem">Sharan Foundation Diagnostic v1.0</p>';
echo '</body></html>';
