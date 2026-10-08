<?php
$page_title = 'Security Check';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php';

// ============================================================
// HANDLE QUICK FIX ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $do = $_POST['do'] ?? '';
    if ($do === 'delete_install') {
        $install_path = __DIR__ . '/../install.php';
        if (is_file($install_path)) {
            if (@unlink($install_path)) flash_set('success', '✓ install.php deleted successfully.');
            else flash_set('error', 'Could not delete install.php — check file permissions and remove manually.');
        }
        redirect(ADMIN_URL . 'security_check.php');
    }
    if ($do === 'change_admin_password') {
        $new = trim($_POST['new_password'] ?? '');
        if (strlen($new) < 8) {
            flash_set('error', 'Password must be at least 8 characters.');
        } else {
            $admin_id = current_admin()['id'];
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([$hash, $admin_id]);
            flash_set('success', '✓ Admin password updated successfully.');
        }
        redirect(ADMIN_URL . 'security_check.php');
    }
}

// ============================================================
// RUN ALL SECURITY CHECKS
// ============================================================
$checks = [];

// ---- CRITICAL ----

// install.php present?
$install_present = is_file(__DIR__ . '/../install.php');
$checks[] = [
    'severity'  => $install_present ? 'critical' : 'ok',
    'category'  => 'Installer',
    'title'     => 'install.php file',
    'detail'    => $install_present
        ? '⚠️ <code>install.php</code> is still present in the web root. Anyone can browse to it and reset the admin password!'
        : '✓ install.php has been removed. Good practice.',
    'fix'       => $install_present ? 'delete_install' : null,
    'fix_label' => '🗑 Delete install.php Now',
];

// Default admin password still in use?
$default_pwd_active = false;
try {
    $admin_row = $pdo->query("SELECT password FROM admins WHERE username='admin' LIMIT 1")->fetch();
    if ($admin_row && password_verify('admin123', $admin_row['password'])) {
        $default_pwd_active = true;
    }
} catch (Throwable $e) {}
$checks[] = [
    'severity' => $default_pwd_active ? 'critical' : 'ok',
    'category' => 'Authentication',
    'title'    => 'Default admin password',
    'detail'   => $default_pwd_active
        ? '⚠️ The admin account is still using the default password <code>admin123</code>. Change it immediately!'
        : '✓ Default password has been changed.',
    'fix'       => $default_pwd_active ? 'show_password_form' : null,
    'fix_label' => '🔑 Change Password Now',
];

// HTTPS?
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
$is_localhost = in_array(($_SERVER['HTTP_HOST'] ?? ''), ['localhost','127.0.0.1','::1']) || strpos(($_SERVER['HTTP_HOST'] ?? ''), 'localhost:') === 0;
$checks[] = [
    'severity' => $is_https ? 'ok' : ($is_localhost ? 'info' : 'critical'),
    'category' => 'Encryption',
    'title'    => 'HTTPS (SSL/TLS)',
    'detail'   => $is_https
        ? '✓ Site is served over HTTPS — donor data, passwords, and payment info are encrypted.'
        : ($is_localhost
            ? 'ℹ️ Running on localhost — HTTPS not required for local development.'
            : '⚠️ Site is on plain HTTP! Donations, passwords, and personal data are sent unencrypted. Get a free Let\'s Encrypt SSL certificate.'),
];

// ---- HIGH ----

// Sensitive folders protected?
$sensitive_paths = [
    'config/.htaccess'     => 'config/ folder',
    'includes/.htaccess'   => 'includes/ folder',
    'logs/.htaccess'       => 'logs/ folder',
    'uploads/.htaccess'    => 'uploads/ folder (no PHP execution)',
    'api/payment/.htaccess'=> 'payment helpers',
];
foreach ($sensitive_paths as $path => $label) {
    $exists = is_file(__DIR__ . '/../' . $path);
    $checks[] = [
        'severity' => $exists ? 'ok' : 'high',
        'category' => 'File Protection',
        'title'    => $label,
        'detail'   => $exists
            ? '✓ <code>' . htmlspecialchars($path) . '</code> exists.'
            : '⚠️ Missing <code>' . htmlspecialchars($path) . '</code> — directory may be browsable / scripts executable.',
    ];
}

// Payment gateway mode
$gw_mode = 'sandbox';
try { $gw_mode = $pdo->query("SELECT gateway_mode FROM settings WHERE id=1")->fetchColumn() ?: 'sandbox'; } catch (Throwable $e) {}
$checks[] = [
    'severity' => 'info',
    'category' => 'Payments',
    'title'    => 'Payment gateway mode',
    'detail'   => $gw_mode === 'live'
        ? '🟢 Gateways are in <strong>LIVE</strong> mode — real payments will be processed.'
        : '🧪 Gateways are in <strong>SANDBOX</strong> mode — no real money will be charged. Switch to Live when ready.',
];

// SMTP configured?
$smtp_ok = false;
try {
    $smtp = $pdo->query("SELECT smtp_host, smtp_username FROM settings WHERE id=1")->fetch();
    $smtp_ok = !empty($smtp['smtp_host']) && !empty($smtp['smtp_username']);
} catch (Throwable $e) {}
$checks[] = [
    'severity' => $smtp_ok ? 'ok' : 'medium',
    'category' => 'Email',
    'title'    => 'SMTP delivery configured',
    'detail'   => $smtp_ok
        ? '✓ SMTP is configured. Receipt + notification emails will reliably deliver.'
        : '⚠️ SMTP is not configured. Email notifications will fall back to PHP\'s mail() and may not arrive.',
];

// ---- MEDIUM ----

// Multiple admins?
$admin_count = 0;
try { $admin_count = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn(); } catch (Throwable $e) {}
$checks[] = [
    'severity' => $admin_count >= 2 ? 'ok' : 'info',
    'category' => 'Authentication',
    'title'    => 'Admin user count',
    'detail'   => $admin_count >= 2
        ? "✓ {$admin_count} admin users — good redundancy."
        : '⚠️ Only 1 admin user exists. If this account is lost, you may be locked out. Consider creating a backup admin.',
];

// PHP version
$php_ok = version_compare(PHP_VERSION, APP_MIN_PHP_VERSION, '>=');
$checks[] = [
    'severity' => $php_ok ? 'ok' : 'high',
    'category' => 'Environment',
    'title'    => 'PHP version',
    'detail'   => $php_ok
        ? '✓ PHP <code>' . PHP_VERSION . '</code> meets the minimum required <code>' . APP_MIN_PHP_VERSION . '</code>.'
        : '⚠️ PHP <code>' . PHP_VERSION . '</code> is below the required <code>' . APP_MIN_PHP_VERSION . '</code>. Upgrade strongly recommended.',
];

// PHP error display (should be off in production)
$display_errors = ini_get('display_errors');
$checks[] = [
    'severity' => $display_errors ? ($is_localhost ? 'info' : 'medium') : 'ok',
    'category' => 'Environment',
    'title'    => 'PHP error display',
    'detail'   => $display_errors
        ? ($is_localhost
            ? 'ℹ️ display_errors=ON (acceptable for localhost development).'
            : '⚠️ display_errors is ON — error messages may leak sensitive paths/info to visitors. Turn it OFF in php.ini and use error logs instead.')
        : '✓ display_errors=OFF (errors logged but not shown to visitors).',
];

// CURL enabled (required for payment gateways)
$curl_ok = function_exists('curl_init');
$checks[] = [
    'severity' => $curl_ok ? 'ok' : 'high',
    'category' => 'Environment',
    'title'    => 'cURL extension',
    'detail'   => $curl_ok
        ? '✓ cURL is enabled — payment gateways and external APIs will work.'
        : '⚠️ cURL is missing. Razorpay, Stripe, PayPal won\'t function without it.',
];

// OpenSSL
$openssl_ok = extension_loaded('openssl');
$checks[] = [
    'severity' => $openssl_ok ? 'ok' : 'high',
    'category' => 'Environment',
    'title'    => 'OpenSSL extension',
    'detail'   => $openssl_ok
        ? '✓ OpenSSL is loaded — TLS/HTTPS connections to gateways work.'
        : '⚠️ OpenSSL is missing — TLS to SMTP/gateways will fail.',
];

// Uploads folder writability
$upload_dir = __DIR__ . '/../uploads';
$upload_ok = is_dir($upload_dir) && is_writable($upload_dir);
$checks[] = [
    'severity' => $upload_ok ? 'ok' : 'high',
    'category' => 'File System',
    'title'    => 'uploads/ folder writable',
    'detail'   => $upload_ok
        ? '✓ The uploads folder is writable.'
        : '⚠️ uploads/ folder is not writable. Image / video uploads will fail. Run <code>chmod -R 775 uploads/</code>',
];

// CRON_SECRET still default?
$cron_path = __DIR__ . '/../cron/recurring_donations.php';
$cron_default = false;
if (is_file($cron_path)) {
    $cron_content = @file_get_contents($cron_path);
    if ($cron_content && strpos($cron_content, "CHANGE_ME_to_a_long_random_string") !== false) {
        $cron_default = true;
    }
}
$checks[] = [
    'severity' => $cron_default ? 'high' : 'ok',
    'category' => 'Cron Security',
    'title'    => 'Cron secret key',
    'detail'   => $cron_default
        ? '⚠️ The cron job is using the placeholder <code>CRON_SECRET</code>. Anyone could trigger the cron via web. Change it in <code>cron/recurring_donations.php</code> to a long random string.'
        : '✓ Cron secret has been customised.',
];

// Library source (Composer vs bundled)
$lib_info = acts_lib_info();
$checks[] = [
    'severity' => 'info',
    'category' => 'Libraries',
    'title'    => 'PHPMailer / FPDF source',
    'detail'   => $lib_info['composer']
        ? '📦 Composer detected → using <strong>vendor/</strong> dependencies (production-grade).'
        : '📂 Using <strong>bundled</strong> PHPMailer + FPDF copies. Works fine, but for the latest patches run <code>composer install</code>.',
];

// Sessions secure?
$sess_cookie_secure = ini_get('session.cookie_secure');
$sess_cookie_httponly = ini_get('session.cookie_httponly');
$sess_score = ($sess_cookie_httponly ? 1 : 0) + (($sess_cookie_secure || $is_localhost) ? 1 : 0);
$checks[] = [
    'severity' => $sess_score === 2 ? 'ok' : ($sess_score === 1 ? 'medium' : 'high'),
    'category' => 'Sessions',
    'title'    => 'Session cookie flags',
    'detail'   => 'cookie_httponly: <strong>' . ($sess_cookie_httponly ? '✓ ON' : '✗ OFF') . '</strong> &nbsp; · &nbsp; cookie_secure: <strong>' . ($sess_cookie_secure ? '✓ ON' : ($is_localhost ? 'OFF (ok for localhost)' : '✗ OFF')) . '</strong>'
                . (!$sess_cookie_httponly ? ' — Set <code>session.cookie_httponly=1</code> in php.ini' : '')
                . ((!$sess_cookie_secure && !$is_localhost) ? ' — Set <code>session.cookie_secure=1</code> for HTTPS sites' : ''),
];

// Database user permissions (best-effort check)
$db_user_warning = false;
try {
    $grants = $pdo->query("SHOW GRANTS")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($grants as $g) {
        if (stripos($g, 'ALL PRIVILEGES ON *.*') !== false || stripos($g, 'GRANT ALL ON *.*') !== false) {
            $db_user_warning = true;
            break;
        }
    }
} catch (Throwable $e) { /* user lacks SHOW GRANTS perm — that's fine */ }
$checks[] = [
    'severity' => $db_user_warning ? 'medium' : 'ok',
    'category' => 'Database',
    'title'    => 'Database user privileges',
    'detail'   => $db_user_warning
        ? '⚠️ The DB user has <strong>ALL PRIVILEGES ON *.*</strong> (e.g. root). Create a dedicated user with only the required privileges on the <code>' . DB_NAME . '</code> database.'
        : '✓ DB user privileges look scoped (or could not check — also acceptable).',
];

// Compute summary
$severity_order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'info' => 3, 'ok' => 4];
usort($checks, fn($a, $b) => $severity_order[$a['severity']] <=> $severity_order[$b['severity']]);

$counts = ['critical'=>0,'high'=>0,'medium'=>0,'info'=>0,'ok'=>0];
foreach ($checks as $c) $counts[$c['severity']]++;
$total = count($checks);
$pass_count = $counts['ok'];
$grade_score = $total > 0
    ? round(($pass_count + $counts['info']*0.5 - $counts['critical']*2 - $counts['high']*1 - $counts['medium']*0.5) / $total * 100)
    : 0;
$grade_score = max(0, min(100, $grade_score));
$grade = $grade_score >= 90 ? 'A' : ($grade_score >= 75 ? 'B' : ($grade_score >= 60 ? 'C' : ($grade_score >= 40 ? 'D' : 'F')));
$grade_color = $grade_score >= 90 ? '#0b6e4f' : ($grade_score >= 75 ? '#2563eb' : ($grade_score >= 60 ? '#d4a017' : ($grade_score >= 40 ? '#e76f51' : '#c0392b')));

$schema = get_schema_version($pdo);
?>

<div class="page-head">
  <div><h2>🛡️ Security Check</h2><p class="sub">Comprehensive scan of your installation — <?= $total ?> checks.</p></div>
  <a href="?" class="btn btn-outline">🔄 Re-run Scan</a>
</div>

<!-- GRADE CARD -->
<div class="card">
  <div class="card-body" style="display:grid;grid-template-columns:auto 1fr;gap:2rem;align-items:center;padding:2rem">
    <div style="text-align:center">
      <div style="width:120px;height:120px;border-radius:50%;background:<?= $grade_color ?>;color:#fff;display:grid;place-items:center;font-size:3.5rem;font-weight:800;box-shadow:0 10px 30px <?= $grade_color ?>50">
        <?= $grade ?>
      </div>
      <p style="margin:.6rem 0 0;color:#666;font-weight:600"><?= $grade_score ?>/100</p>
    </div>
    <div>
      <h3 style="color:<?= $grade_color ?>;margin-bottom:.4rem">
        <?= $grade_score >= 90 ? '🏆 Excellent — production ready!'
           : ($grade_score >= 75 ? '✓ Good — minor improvements suggested'
           : ($grade_score >= 60 ? '⚠️ Fair — please address warnings'
           : ($grade_score >= 40 ? '⚠️ Risky — multiple issues need attention'
           : '🚨 Critical — DO NOT use in production')))
        ?>
      </h3>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.7rem">
        <span style="background:#c0392b;color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.82rem;font-weight:600">🚨 <?= $counts['critical'] ?> Critical</span>
        <span style="background:#e76f51;color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.82rem;font-weight:600">⚠️ <?= $counts['high'] ?> High</span>
        <span style="background:#d4a017;color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.82rem;font-weight:600">⚠ <?= $counts['medium'] ?> Medium</span>
        <span style="background:#3498db;color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.82rem;font-weight:600">ℹ️ <?= $counts['info'] ?> Info</span>
        <span style="background:#0b6e4f;color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.82rem;font-weight:600">✓ <?= $counts['ok'] ?> Pass</span>
      </div>
      <?php if ($schema): ?>
        <p style="margin-top:1rem;color:#666;font-size:.88rem">
          📦 Schema: <strong><?= e($schema['version']) ?></strong> · Code: <strong><?= e(APP_VERSION) ?></strong>
          <?php if ($schema['version'] !== APP_VERSION): ?>
            <span style="color:#c0392b">⚠️ Mismatch — re-run install.php</span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- CHECKS LIST -->
<div class="card">
  <div class="card-head"><h3>📋 Detailed Checks</h3></div>
  <div class="card-body" style="padding:0">
    <?php
    $sev_meta = [
        'critical' => ['🚨','#c0392b','#fdecea','CRITICAL'],
        'high'     => ['⚠️','#e76f51','#fff4ee','HIGH'],
        'medium'   => ['⚠','#d4a017','#fef7e0','MEDIUM'],
        'info'     => ['ℹ️','#3498db','#eaf2ff','INFO'],
        'ok'       => ['✓','#0b6e4f','#e8f5ef','PASS'],
    ];
    foreach ($checks as $c):
        [$ico, $color, $bg, $label] = $sev_meta[$c['severity']];
    ?>
      <div style="padding:1rem 1.5rem;border-bottom:1px solid #eee;display:grid;grid-template-columns:auto 1fr auto;gap:1rem;align-items:center">
        <div style="background:<?= $bg ?>;color:<?= $color ?>;width:42px;height:42px;border-radius:50%;display:grid;place-items:center;font-size:1.2rem;flex-shrink:0"><?= $ico ?></div>
        <div>
          <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:.2rem">
            <strong style="color:<?= $color ?>;font-size:.78rem;letter-spacing:1px"><?= $label ?></strong>
            <span style="color:#888;font-size:.78rem">·</span>
            <span style="color:#666;font-size:.78rem;text-transform:uppercase;letter-spacing:1px"><?= e($c['category']) ?></span>
          </div>
          <div style="font-weight:600;color:#333;margin-bottom:.25rem"><?= e($c['title']) ?></div>
          <div style="color:#666;font-size:.92rem;line-height:1.55"><?= $c['detail'] ?></div>
        </div>
        <div>
          <?php if (!empty($c['fix']) && $c['fix']==='delete_install'): ?>
            <form method="post" onsubmit="return confirm('Delete install.php from the server? This cannot be undone.')" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="delete_install">
              <button class="btn-sm" style="background:<?= $color ?>;color:#fff;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-weight:600;font-size:.82rem"><?= e($c['fix_label']) ?></button>
            </form>
          <?php elseif (!empty($c['fix']) && $c['fix']==='show_password_form'): ?>
            <button onclick="document.getElementById('pwForm').scrollIntoView({behavior:'smooth'})" style="background:<?= $color ?>;color:#fff;border:none;padding:.5rem 1rem;border-radius:6px;cursor:pointer;font-weight:600;font-size:.82rem"><?= e($c['fix_label']) ?></button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- QUICK-FIX: Change Password -->
<?php if ($default_pwd_active): ?>
<form method="post" id="pwForm" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="change_admin_password">
  <div class="card-head" style="background:#fdecea"><h3 style="color:#c0392b">🔑 Change Default Admin Password</h3></div>
  <div class="card-body">
    <p style="margin-bottom:1rem;color:#555">Set a new password for your account (<strong><?= e(current_admin()['username']) ?></strong>). Minimum 8 characters.</p>
    <div class="form-row">
      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="new_password" required minlength="8" placeholder="At least 8 characters" autocomplete="new-password">
      </div>
      <div class="form-group" style="display:flex;align-items:flex-end">
        <button class="btn" style="background:#c0392b;color:#fff;padding:.85rem 1.5rem;border:none;border-radius:6px;cursor:pointer;font-weight:600">🔑 Change Password</button>
      </div>
    </div>
  </div>
</form>
<?php endif; ?>

<!-- SCHEMA HISTORY -->
<?php $history = get_schema_history($pdo); if ($history): ?>
<div class="card">
  <div class="card-head"><h3>📜 Schema Version History</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table>
      <thead><tr><th>Version</th><th>Description</th><th>Applied</th><th>By</th><th>Notes</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td><strong style="color:var(--primary)"><?= e($h['version']) ?></strong></td>
          <td><?= e($h['description']) ?></td>
          <td style="font-size:.85rem;white-space:nowrap"><?= e($h['applied_at']) ?><br><small style="color:#888"><?= time_ago($h['applied_at']) ?></small></td>
          <td><?= e($h['applied_by']) ?></td>
          <td style="font-size:.85rem;color:#666;max-width:340px"><?= e($h['notes']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
