<?php
$page_title = 'Email Log';
require_once __DIR__ . '/includes/header.php';

$log_file = __DIR__ . '/../logs/mail.log';

// Clear log
if (isset($_POST['clear']) && csrf_check($_POST['csrf'] ?? '')) {
    @file_put_contents($log_file, '');
    flash_set('success', 'Email log cleared.');
    redirect(ADMIN_URL . 'email_log.php');
}

$lines = [];
if (is_file($log_file)) {
    $raw = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($raw); // newest first
}
?>

<div class="page-head">
  <div><h2>Email Log</h2><p class="sub">Outgoing email notifications from your website.</p></div>
  <?php if ($lines): ?>
  <form method="post" class="del-form" style="display:inline">
    <?= csrf_field() ?>
    <button name="clear" value="1" class="btn" style="background:#e74c3c;color:#fff">🗑 Clear Log</button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h3>📨 Mail Activity (<?= count($lines) ?> entries)</h3>
    <small style="color:#888">Log file: <code>logs/mail.log</code></small>
  </div>
  <div class="card-body">
    <?php if (!$lines): ?>
      <div class="empty">
        <div class="ico">📭</div>
        <h3>No emails sent yet</h3>
        <p>When form submissions trigger notifications, they will be logged here.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>Status</th><th>To</th><th>Subject</th></tr></thead>
        <tbody>
        <?php foreach ($lines as $line):
          // Parse: [2026-06-06 10:15:32] SENT | To: admin@x.com | Subject: ...
          if (!preg_match('/^\[([^\]]+)\] (\S+(?:\s\([^)]*\))?) \| To: ([^|]+) \| Subject: (.+)$/', $line, $m)) continue;
          $ok = stripos($m[2], 'SENT') === 0; ?>
          <tr style="<?= !$ok ? 'background:#fff8f0' : '' ?>">
            <td style="white-space:nowrap;color:#666;font-size:.85rem"><?= e($m[1]) ?></td>
            <td><span class="status-badge <?= $ok ? 'status-active' : 'status-new' ?>"><?= $ok ? '✓ Sent' : '✕ Failed' ?></span><?= !$ok ? '<br><small style="color:#c0392b">' . e(trim(str_replace('FAILED','',$m[2]),' ()')) . '</small>' : '' ?></td>
            <td><?= e(trim($m[3])) ?></td>
            <td><?= e($m[4]) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>⚙️ Email Configuration</h3></div>
  <div class="card-body">
    <p style="margin-bottom:1rem;color:#555">Email notifications are sent automatically when:</p>
    <ul style="margin-left:1.5rem;color:#555;line-height:1.9">
      <li>🤝 Someone submits a Volunteer application → admin + applicant get notified</li>
      <li>🏢 Someone submits a Partnership inquiry → admin + contact get notified</li>
      <li>✉️ Someone sends a Contact message → admin + sender get notified</li>
      <li>📧 Someone subscribes to the newsletter → subscriber gets a welcome email</li>
    </ul>

    <div style="margin-top:1.5rem;padding:1rem;background:#fef7e0;border-left:3px solid #d4a017;border-radius:6px;font-size:.9rem;color:#5b4a2c">
      <strong>⚠️ XAMPP / Local Setup Note:</strong><br>
      PHP's <code>mail()</code> function requires a configured SMTP server. For local testing on XAMPP:
      <ol style="margin-top:.5rem;margin-left:1.4rem">
        <li>Open <code>C:\xampp\php\php.ini</code> and configure <code>[mail function]</code> with your SMTP settings, OR</li>
        <li>Use <strong>Mercury Mail</strong> bundled with XAMPP, OR</li>
        <li>Edit <code>includes/mailer.php</code> and integrate <strong>PHPMailer</strong> with Gmail SMTP for production use.</li>
      </ol>
      Submissions will still be saved to the database even if emails fail to send.
    </div>

    <div style="margin-top:1rem;padding:1rem;background:#e8f5ef;border-left:3px solid #2563eb;border-radius:6px;font-size:.9rem;color:#1d4ed8">
      <strong>To change recipient/sender addresses:</strong> Edit <code>includes/mailer.php</code> and update <code>MAIL_FROM_EMAIL</code> and <code>ADMIN_NOTIFY_EMAIL</code> constants.
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
