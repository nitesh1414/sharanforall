<?php
$page_title = 'Payment Gateways';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/payments.php';

// ===== SAVE GATEWAY SETTINGS =====
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? '')) {
    $fields = [
        'gateway_mode',
        'razorpay_enabled','razorpay_key_id','razorpay_key_secret','razorpay_webhook_secret',
        'stripe_enabled','stripe_public_key','stripe_secret_key','stripe_webhook_secret',
        'paypal_enabled','paypal_client_id','paypal_client_secret','paypal_webhook_id',
    ];
    $data = [];
    foreach ($fields as $f) {
        if (in_array($f, ['razorpay_enabled','stripe_enabled','paypal_enabled'])) {
            $data[$f] = isset($_POST[$f]) ? 1 : 0;
        } else {
            $data[$f] = trim($_POST[$f] ?? '');
        }
    }
    // Don't overwrite secrets if blank (keep existing value)
    foreach (['razorpay_key_secret','razorpay_webhook_secret','stripe_secret_key','stripe_webhook_secret','paypal_client_secret','paypal_webhook_id'] as $secret_field) {
        if ($data[$secret_field] === '') unset($data[$secret_field]);
    }
    $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($data)));
    $pdo->prepare("UPDATE settings SET $set WHERE id=1")->execute($data);
    $saved = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: $data;
    flash_saved('updated', 'Payment gateway settings', $saved);
    redirect(ADMIN_URL . 'payments.php');
}

$s = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$gateways = available_gateways();

// Recent webhook events
$recent_webhooks = [];
try { $recent_webhooks = $pdo->query("SELECT * FROM webhook_events ORDER BY received_at DESC LIMIT 15")->fetchAll(); } catch (Throwable $e) {}
?>

<div class="page-head">
  <div><h2>💳 Payment Gateways</h2><p class="sub">Configure Razorpay, Stripe & PayPal for live donation processing.</p></div>
</div>

<!-- STATUS OVERVIEW -->
<div class="card">
  <div class="card-head"><h3>🚦 Integration Status</h3></div>
  <div class="card-body">
    <p style="margin-bottom:1rem;color:#555">
      <strong>Current mode:</strong>
      <span style="background:<?= $s['gateway_mode']==='live'?'#2563eb':'#d4a017' ?>;color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.85rem;font-weight:600;text-transform:uppercase">
        <?= $s['gateway_mode']==='live' ? '🟢 LIVE' : '🧪 SANDBOX' ?>
      </span>
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
      <?php foreach (['razorpay'=>'Razorpay','stripe'=>'Stripe','paypal'=>'PayPal'] as $gw => $name):
        $enabled = !empty($s[$gw . '_enabled']);
        $configured = has_gateway_credentials($gw);
        $ready = $enabled && $configured;
        $color = $ready ? '#2563eb' : ($enabled ? '#d4a017' : '#c0392b'); ?>
        <div style="background:#fff;border:1px solid #eee;border-left:4px solid <?= $color ?>;border-radius:10px;padding:1.2rem">
          <h4 style="color:<?= $color ?>;margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem">
            <?= $ready ? '✓' : ($enabled ? '⚠' : '✗') ?> <?= $name ?>
          </h4>
          <p style="font-size:.85rem;color:#666;margin:0">
            <?php if ($ready): ?>Ready to accept payments.
            <?php elseif ($enabled): ?>Enabled but credentials missing/invalid.
            <?php else: ?>Disabled.<?php endif; ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-head"><h3>⚙️ Gateway Mode</h3></div>
  <div class="card-body">
    <div class="form-group">
      <label>Gateway Mode</label>
      <select name="gateway_mode" style="max-width:260px">
        <option value="sandbox" <?= $s['gateway_mode']==='sandbox'?'selected':'' ?>>🧪 Sandbox / Test (no real money)</option>
        <option value="live" <?= $s['gateway_mode']==='live'?'selected':'' ?>>🟢 Live (real payments)</option>
      </select>
      <p class="help">Switch to <strong>Live</strong> only after thoroughly testing with sandbox/test keys.</p>
    </div>
  </div>

  <!-- ============ RAZORPAY ============ -->
  <div class="card-head" id="razorpay" style="background:#fff7eb"><h3>💳 Razorpay (India — INR primary)</h3></div>
  <div class="card-body">
    <label class="checkbox-row" style="margin-bottom:1rem;font-weight:600">
      <input type="checkbox" name="razorpay_enabled" value="1" <?= $s['razorpay_enabled']?'checked':'' ?> style="width:18px;height:18px;margin-right:.5rem;accent-color:var(--primary)">
      Enable Razorpay
    </label>

    <div class="form-row">
      <div class="form-group"><label>Key ID</label><input type="text" name="razorpay_key_id" value="<?= e($s['razorpay_key_id']) ?>" placeholder="rzp_test_xxxxxxxxxxxx or rzp_live_xxxxxxxxxxxx" autocomplete="off"></div>
      <div class="form-group"><label>Key Secret</label><input type="password" name="razorpay_key_secret" value="" placeholder="<?= !empty($s['razorpay_key_secret']) ? '••••••••• (leave blank to keep existing)' : 'Your secret key' ?>" autocomplete="new-password"></div>
    </div>
    <div class="form-group">
      <label>Webhook Secret</label>
      <input type="password" name="razorpay_webhook_secret" value="" placeholder="<?= !empty($s['razorpay_webhook_secret']) ? '••••••••• (leave blank to keep existing)' : 'Set this in Razorpay dashboard → Webhooks' ?>" autocomplete="new-password">
      <p class="help">Webhook URL to configure in Razorpay dashboard: <code><?= e(BASE_URL) ?>api/payment/razorpay_webhook.php</code></p>
    </div>
    <div style="background:#fff7eb;border-left:3px solid #f4a261;padding:.8rem 1rem;border-radius:6px;font-size:.85rem;color:#5b4a2c">
      <strong>📌 Get keys:</strong> <a href="https://dashboard.razorpay.com/app/keys" target="_blank">dashboard.razorpay.com/app/keys</a>
      <br><strong>Test cards:</strong> 4111 1111 1111 1111 (Visa), any CVV, future expiry. Test UPI: success@razorpay
    </div>
  </div>

  <!-- ============ STRIPE ============ -->
  <div class="card-head" id="stripe" style="background:#eaf3ff"><h3>💳 Stripe (Global Cards + Apple/Google Pay)</h3></div>
  <div class="card-body">
    <label class="checkbox-row" style="margin-bottom:1rem;font-weight:600">
      <input type="checkbox" name="stripe_enabled" value="1" <?= $s['stripe_enabled']?'checked':'' ?> style="width:18px;height:18px;margin-right:.5rem;accent-color:var(--primary)">
      Enable Stripe
    </label>

    <div class="form-row">
      <div class="form-group"><label>Publishable Key</label><input type="text" name="stripe_public_key" value="<?= e($s['stripe_public_key']) ?>" placeholder="pk_test_... or pk_live_..." autocomplete="off"></div>
      <div class="form-group"><label>Secret Key</label><input type="password" name="stripe_secret_key" value="" placeholder="<?= !empty($s['stripe_secret_key']) ? '••••••••• (leave blank to keep existing)' : 'sk_test_... or sk_live_...' ?>" autocomplete="new-password"></div>
    </div>
    <div class="form-group">
      <label>Webhook Signing Secret</label>
      <input type="password" name="stripe_webhook_secret" value="" placeholder="<?= !empty($s['stripe_webhook_secret']) ? '••••••••• (leave blank to keep existing)' : 'whsec_...' ?>" autocomplete="new-password">
      <p class="help">Webhook URL to configure in Stripe dashboard: <code><?= e(BASE_URL) ?>api/payment/stripe_webhook.php</code></p>
    </div>
    <div style="background:#eaf3ff;border-left:3px solid #3498db;padding:.8rem 1rem;border-radius:6px;font-size:.85rem;color:#1a4d6e">
      <strong>📌 Get keys:</strong> <a href="https://dashboard.stripe.com/apikeys" target="_blank">dashboard.stripe.com/apikeys</a>
      <br><strong>Test cards:</strong> 4242 4242 4242 4242 (success), 4000 0000 0000 9995 (decline), any CVV, future expiry
    </div>
  </div>

  <!-- ============ PAYPAL ============ -->
  <div class="card-head" id="paypal" style="background:#fff9e6"><h3>🅿️ PayPal (Global)</h3></div>
  <div class="card-body">
    <label class="checkbox-row" style="margin-bottom:1rem;font-weight:600">
      <input type="checkbox" name="paypal_enabled" value="1" <?= $s['paypal_enabled']?'checked':'' ?> style="width:18px;height:18px;margin-right:.5rem;accent-color:var(--primary)">
      Enable PayPal
    </label>

    <div class="form-row">
      <div class="form-group"><label>Client ID</label><input type="text" name="paypal_client_id" value="<?= e($s['paypal_client_id']) ?>" placeholder="Your PayPal app Client ID" autocomplete="off"></div>
      <div class="form-group"><label>Client Secret</label><input type="password" name="paypal_client_secret" value="" placeholder="<?= !empty($s['paypal_client_secret']) ? '••••••••• (leave blank to keep existing)' : 'Your PayPal app Secret' ?>" autocomplete="new-password"></div>
    </div>
    <div class="form-group">
      <label>Webhook ID</label>
      <input type="text" name="paypal_webhook_id" value="<?= e($s['paypal_webhook_id']) ?>" placeholder="From PayPal dashboard → Webhooks">
      <p class="help">Webhook URL to configure in PayPal dashboard: <code><?= e(BASE_URL) ?>api/payment/paypal_webhook.php</code></p>
    </div>
    <div style="background:#fff9e6;border-left:3px solid #d4a017;padding:.8rem 1rem;border-radius:6px;font-size:.85rem;color:#5b4a2c">
      <strong>📌 Get credentials:</strong> <a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank">developer.paypal.com/dashboard/applications</a>
      <br><strong>Test:</strong> Use a sandbox personal account from your PayPal Developer Dashboard
    </div>
  </div>

  <div class="card-body">
    <div class="form-actions">
      <button class="btn btn-primary">💾 Save Settings</button>
      <a href="<?= BASE_URL ?>pages/donate.php" target="_blank" class="btn btn-outline">🌐 Test on Donation Page</a>
    </div>
  </div>
</form>

<!-- WEBHOOK ACTIVITY -->
<div class="card">
  <div class="card-head"><h3>📡 Recent Webhook Events</h3></div>
  <div class="card-body">
    <?php if (!$recent_webhooks): ?>
      <div class="empty">
        <div class="ico">📡</div>
        <h3>No webhook events yet</h3>
        <p>Once gateways send events (after a successful payment, subscription charge, etc.), they will appear here.</p>
      </div>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>When</th><th>Gateway</th><th>Event Type</th><th>Signature</th><th>Donation</th><th>Result</th></tr></thead>
      <tbody>
        <?php foreach ($recent_webhooks as $w): ?>
        <tr>
          <td style="white-space:nowrap;font-size:.85rem;color:#666"><?= e($w['received_at']) ?><br><small><?= time_ago($w['received_at']) ?></small></td>
          <td><span class="status-badge" style="background:#e8eef5;color:#555;text-transform:capitalize"><?= e($w['gateway']) ?></span></td>
          <td><code style="font-size:.78rem"><?= e($w['event_type']) ?></code></td>
          <td><?= $w['signature_ok'] ? '<span style="color:#2563eb">✓ valid</span>' : '<span style="color:#c0392b">✗ invalid</span>' ?></td>
          <td><?php if ($w['donation_id']): ?><a href="<?= ADMIN_URL ?>donations.php?view=<?= (int)$w['donation_id'] ?>">#<?= (int)$w['donation_id'] ?></a><?php else: ?>—<?php endif; ?></td>
          <td style="font-size:.85rem;color:#555;max-width:300px"><?= e($w['result']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__.'/includes/footer.php'; ?>
