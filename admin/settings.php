<?php
$page_title = 'Site Settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/mailer.php';

// ---- TEST EMAIL ----
if (isset($_POST['test_email']) && csrf_check($_POST['csrf'] ?? '')) {
    $to = trim($_POST['test_to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Please enter a valid email to test.');
    } else {
        [$ok, $err] = send_mail($to, '✉️ Test Email from Sharan Foundation',
            email_template('Email Test Successful!', '<p>Hello! 👋</p><p>This is a <strong>test email</strong> from your Sharan Foundation website. If you are seeing this, your SMTP configuration is working correctly! 🎉</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>'));
        if ($ok) flash_set('success', '✓ Test email sent successfully to ' . e($to) . '. Check the inbox (and spam folder).');
        else     flash_set('error', '✕ Send failed: ' . e($err ?: 'unknown error') . '. Check SMTP settings.');
    }
    redirect(ADMIN_URL . 'settings.php#email');
}

// ---- SAVE SETTINGS ----
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $fields = [
        'site_title','tagline','email_in','email_uk','phone_in','phone_uk',
        'address_in','address_uk','facebook','instagram','twitter','youtube',
        'about_short','mission','vision','values_text',
        'story_intro','story_intro_hi','vision_statement','vision_statement_hi',
        'guiding_scripture','scripture_reference','motto','motto_hi','locations_served',
        'smtp_host','smtp_port','smtp_username','smtp_password',
        'smtp_encryption','smtp_from_email','smtp_from_name','admin_notify_email',
        'default_language'
    ];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');
    // Don't overwrite saved SMTP password if blank
    if ($data['smtp_password'] === '') {
        unset($data['smtp_password']);
    }
    $set = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
    $pdo->prepare("UPDATE settings SET $set WHERE id=1")->execute($data);
    $saved = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: $data;
    flash_saved('updated', 'Site settings', $saved);
    redirect(ADMIN_URL.'settings.php');
}

$s = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
?>
<div class="page-head"><div><h2>Site Settings</h2><p class="sub">General information shown across your website.</p></div></div>

<form method="post" class="card"><?= csrf_field() ?>
  <div class="card-head"><h3>🏷️ Identity</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Site Title</label><input type="text" name="site_title" value="<?= e($s['site_title']) ?>"></div>
      <div class="form-group"><label>Tagline</label><input type="text" name="tagline" value="<?= e($s['tagline']) ?>"></div>
    </div>
    <div class="form-group"><label>About (Short Description)</label><textarea name="about_short" rows="2"><?= e($s['about_short']) ?></textarea></div>
  </div>

  <div class="card-head"><h3>🎯 Mission, Vision, Values</h3></div>
  <div class="card-body">
    <div class="form-group"><label>Mission</label><textarea name="mission" rows="2"><?= e($s['mission']) ?></textarea></div>
    <div class="form-group"><label>Vision</label><textarea name="vision" rows="2"><?= e($s['vision']) ?></textarea></div>
    <div class="form-group"><label>Values</label><textarea name="values_text" rows="2"><?= e($s['values_text']) ?></textarea></div>
  </div>

  <!-- STORY & VISION STATEMENT -->
  <div class="card-head"><h3>📖 Our Story &amp; Vision Statement</h3></div>
  <div class="card-body">
    <div class="form-group"><label>Story Intro <small style="color:#888;font-weight:400">(shown on About page intro)</small></label><textarea name="story_intro" rows="4"><?= e($s['story_intro'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Story Intro (Hindi)</label><textarea name="story_intro_hi" rows="4"><?= e($s['story_intro_hi'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Vision Statement <small style="color:#888;font-weight:400">(long-form quote)</small></label><textarea name="vision_statement" rows="3"><?= e($s['vision_statement'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Vision Statement (Hindi)</label><textarea name="vision_statement_hi" rows="3"><?= e($s['vision_statement_hi'] ?? '') ?></textarea></div>
  </div>

  <!-- GUIDING SCRIPTURE & MOTTO -->
  <div class="card-head"><h3>✝️ Guiding Scripture &amp; Motto</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Guiding Scripture (verse text)</label><textarea name="guiding_scripture" rows="3"><?= e($s['guiding_scripture'] ?? '') ?></textarea></div>
      <div class="form-group"><label>Scripture Reference</label><input type="text" name="scripture_reference" value="<?= e($s['scripture_reference'] ?? '') ?>" placeholder="Deuteronomy 10:18"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Motto</label><input type="text" name="motto" value="<?= e($s['motto'] ?? '') ?>" placeholder="Educate • Equip • Empower • Transform"></div>
      <div class="form-group"><label>Motto (Hindi)</label><input type="text" name="motto_hi" value="<?= e($s['motto_hi'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Locations Served <small style="color:#888;font-weight:400">(shown in footer / hero)</small></label><input type="text" name="locations_served" value="<?= e($s['locations_served'] ?? '') ?>" placeholder="India &amp; Nepal"></div>
  </div>

  <div class="card-head"><h3>🇮🇳 India Office</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Email (India)</label><input type="email" name="email_in" value="<?= e($s['email_in']) ?>"></div>
      <div class="form-group"><label>Phone (India)</label><input type="text" name="phone_in" value="<?= e($s['phone_in']) ?>"></div>
    </div>
    <div class="form-group"><label>Address (India)</label><textarea name="address_in" rows="2"><?= e($s['address_in']) ?></textarea></div>
  </div>

  <div class="card-head"><h3>🇬🇧 UK Office</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Email (UK)</label><input type="email" name="email_uk" value="<?= e($s['email_uk']) ?>"></div>
      <div class="form-group"><label>Phone (UK)</label><input type="text" name="phone_uk" value="<?= e($s['phone_uk']) ?>"></div>
    </div>
    <div class="form-group"><label>Address (UK)</label><textarea name="address_uk" rows="2"><?= e($s['address_uk']) ?></textarea></div>
  </div>

  <div class="card-head"><h3>🔗 Social Media</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group"><label>Facebook URL</label><input type="url" name="facebook" value="<?= e($s['facebook']) ?>"></div>
      <div class="form-group"><label>Instagram URL</label><input type="url" name="instagram" value="<?= e($s['instagram']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Twitter / X URL</label><input type="url" name="twitter" value="<?= e($s['twitter']) ?>"></div>
      <div class="form-group"><label>YouTube URL</label><input type="url" name="youtube" value="<?= e($s['youtube']) ?>"></div>
    </div>
  </div>

  <div class="card-head" id="language"><h3>🌐 Language</h3></div>
  <div class="card-body">
    <input type="hidden" name="default_language" value="en">
    <div style="background:#eaf2ff;border-left:4px solid #2563eb;padding:.9rem 1.1rem;border-radius:6px">
      <strong>Google Translate handles site languages.</strong>
      <p style="margin:.4rem 0 0;color:#40506c">The website is translated automatically by Google Translate. Choose which languages visitors can switch to on the <a href="<?= ADMIN_URL ?>languages.php" style="color:#2563eb;font-weight:600">🌐 Languages &amp; Translation</a> page. Content is entered once, in English — no manual translations needed.</p>
    </div>
  </div>

  <!-- SMTP / EMAIL -->
  <div class="card-head" id="email"><h3>📧 Email (SMTP) Configuration</h3></div>
  <div class="card-body">
    <p style="color:#666;margin-bottom:1rem;font-size:.92rem">
      Configure SMTP to reliably send email notifications via Gmail, SendGrid, Mailgun, your hosting provider, etc.
      Leave SMTP Host blank to fall back to PHP's native <code>mail()</code> function.
    </p>

    <div class="form-row">
      <div class="form-group"><label>SMTP Host</label>
        <input type="text" name="smtp_host" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
      </div>
      <div class="form-group"><label>SMTP Port</label>
        <input type="number" name="smtp_port" value="<?= e($s['smtp_port'] ?? 587) ?>" placeholder="587">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>SMTP Username</label>
        <input type="text" name="smtp_username" value="<?= e($s['smtp_username'] ?? '') ?>" placeholder="you@gmail.com" autocomplete="off">
      </div>
      <div class="form-group"><label>SMTP Password</label>
        <input type="password" name="smtp_password" value="" placeholder="<?= !empty($s['smtp_password']) ? '••••••••• (leave blank to keep existing)' : 'Your SMTP / App Password' ?>" autocomplete="new-password">
        <p class="help">For Gmail: use an <strong>App Password</strong> (not your Gmail password). Generate one at myaccount.google.com → Security → App passwords.</p>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Encryption</label>
        <select name="smtp_encryption">
          <option value="tls"  <?= ($s['smtp_encryption'] ?? 'tls')==='tls'?'selected':'' ?>>TLS (port 587 — recommended)</option>
          <option value="ssl"  <?= ($s['smtp_encryption'] ?? '')==='ssl'?'selected':'' ?>>SSL (port 465)</option>
          <option value="none" <?= ($s['smtp_encryption'] ?? '')==='none'?'selected':'' ?>>None (port 25 — not recommended)</option>
        </select>
      </div>
      <div class="form-group"><label>From Name</label>
        <input type="text" name="smtp_from_name" value="<?= e($s['smtp_from_name'] ?? 'Sharan Foundation') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>From Email</label>
        <input type="email" name="smtp_from_email" value="<?= e($s['smtp_from_email'] ?? '') ?>" placeholder="noreply@sharanforall.org">
      </div>
      <div class="form-group"><label>Admin Notification Email</label>
        <input type="email" name="admin_notify_email" value="<?= e($s['admin_notify_email'] ?? '') ?>" placeholder="admin@sharanforall.org">
        <p class="help">Where new submission alerts will be sent.</p>
      </div>
    </div>

    <div style="margin-top:1rem;padding:1rem;background:#fef7e0;border-left:3px solid #d4a017;border-radius:6px;font-size:.88rem;color:#5b4a2c">
      <strong>📌 Common SMTP Settings:</strong>
      <ul style="margin:.5rem 0 0 1.5rem;line-height:1.8">
        <li><strong>Gmail:</strong> <code>smtp.gmail.com</code> • Port <code>587</code> • TLS • Use App Password</li>
        <li><strong>Outlook/Office 365:</strong> <code>smtp-mail.outlook.com</code> • Port <code>587</code> • TLS</li>
        <li><strong>SendGrid:</strong> <code>smtp.sendgrid.net</code> • Port <code>587</code> • TLS • Username = <code>apikey</code></li>
        <li><strong>Mailgun:</strong> <code>smtp.mailgun.org</code> • Port <code>587</code> • TLS</li>
      </ul>
    </div>
  </div>

  <div class="card-body">
    <div class="form-actions"><button class="btn btn-primary">💾 Save All Settings</button></div>
  </div>
</form>

<!-- TEST EMAIL FORM -->
<form method="post" class="card" id="test-email-form">
  <?= csrf_field() ?>
  <div class="card-head"><h3>🧪 Send Test Email</h3></div>
  <div class="card-body">
    <p style="color:#666;margin-bottom:1rem">Save your SMTP settings above first, then send a test email to verify everything works.</p>
    <div style="display:flex;gap:.7rem;align-items:flex-end">
      <div class="form-group" style="flex:1;margin:0">
        <label>Send test email to:</label>
        <input type="email" name="test_to" placeholder="your@email.com" value="<?= e($s['admin_notify_email'] ?? '') ?>" required>
      </div>
      <button type="submit" name="test_email" value="1" class="btn btn-accent">📨 Send Test</button>
    </div>
  </div>
</form>

<?php require __DIR__.'/includes/footer.php'; ?>
