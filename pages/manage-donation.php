<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if (!$token || strlen($token) < 16) {
    http_response_code(404);
    $page_title = 'Invalid Link';
    $current_page = '';
    require __DIR__ . '/../includes/public_header.php';
    echo '<section style="padding:5rem 1rem;text-align:center"><h2>🔒 Invalid or expired link</h2><p style="color:var(--gray);margin:1rem 0">This subscription management link is invalid. Please check the link from your email or contact us.</p><a href="' . BASE_URL . 'pages/contact.php" class="btn btn-primary">Contact Us</a></section>';
    require __DIR__ . '/../includes/public_footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM recurring_donations WHERE manage_token = ? LIMIT 1");
$stmt->execute([$token]);
$r = $stmt->fetch();

if (!$r) {
    http_response_code(404);
    $page_title = 'Not Found';
    $current_page = '';
    require __DIR__ . '/../includes/public_header.php';
    echo '<section style="padding:5rem 1rem;text-align:center"><h2>🔒 Subscription Not Found</h2><p style="color:var(--gray);margin:1rem 0">We could not find a recurring donation matching this link.</p></section>';
    require __DIR__ . '/../includes/public_footer.php';
    exit;
}

// ----- Handle action -----
$action_msg = $action_type = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    if ($do === 'pause') {
        $pdo->prepare("UPDATE recurring_donations SET status='paused' WHERE id=?")->execute([$r['id']]);
        $r['status'] = 'paused';
        try { notify_recurring_cancelled($r); } catch (Throwable $e) {}
        $action_msg = '⏸ Your recurring donation has been paused. You can resume it any time.';
        $action_type = 'success';
    } elseif ($do === 'resume') {
        // When resuming, push next_charge_date if it has passed
        $next = $r['next_charge_date'];
        if ($next < date('Y-m-d')) {
            $mods = ['weekly'=>'+1 week','monthly'=>'+1 month','quarterly'=>'+3 months','yearly'=>'+1 year'];
            $next = date('Y-m-d', strtotime('today ' . ($mods[$r['frequency']] ?? '+1 month')));
        }
        $pdo->prepare("UPDATE recurring_donations SET status='active', next_charge_date=?, failed_attempts=0 WHERE id=?")
            ->execute([$next, $r['id']]);
        $r['status'] = 'active';
        $r['next_charge_date'] = $next;
        $action_msg = '✓ Your recurring donation has been resumed. Welcome back! 💚';
        $action_type = 'success';
    } elseif ($do === 'cancel') {
        $reason = trim($_POST['reason'] ?? '');
        $pdo->prepare("UPDATE recurring_donations SET status='cancelled', cancelled_at=NOW(), cancellation_reason=? WHERE id=?")
            ->execute([$reason, $r['id']]);
        $r['status'] = 'cancelled';
        $r['cancellation_reason'] = $reason;
        try { notify_recurring_cancelled($r); } catch (Throwable $e) {}
        $action_msg = 'Your recurring donation has been cancelled. Thank you for your past generosity! 🙏';
        $action_type = 'success';
    } elseif ($do === 'update_amount') {
        $new_amount = (float)preg_replace('/[^0-9.]/', '', $_POST['new_amount']);
        if ($new_amount >= 1) {
            $pdo->prepare("UPDATE recurring_donations SET amount=? WHERE id=?")->execute([$new_amount, $r['id']]);
            $r['amount'] = $new_amount;
            $action_msg = '✓ Your donation amount has been updated.';
            $action_type = 'success';
        } else {
            $action_msg = 'Please enter a valid amount.';
            $action_type = 'error';
        }
    }
}

// Recent charges for this donor
$charges = $pdo->prepare("SELECT * FROM recurring_charges WHERE recurring_id=? AND status IN ('success','manual') ORDER BY charged_at DESC LIMIT 20");
$charges->execute([$r['id']]);
$charges = $charges->fetchAll();

$sym = $r['currency'] === 'INR' ? '₹' : ($r['currency'] === 'GBP' ? '£' : '$');

$page_title = 'Manage Your Donation';
$page_desc = 'Manage your recurring donation to Sharan Foundation.';
$current_page = '';

$extra_head = '<style>
  .manage-wrap{max-width:760px;margin:0 auto;padding:2rem 1rem}
  .manage-card{background:#fff;border-radius:14px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:2rem}
  .manage-hero{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;padding:2.5rem;text-align:center}
  .manage-hero .amount{font-size:3rem;color:#f4a261;font-weight:800;margin:.3rem 0}
  .manage-hero .freq{opacity:.9;font-size:1rem}
  .manage-hero .meta-pills{display:flex;justify-content:center;gap:.6rem;margin-top:1rem;flex-wrap:wrap}
  .meta-pill{background:rgba(255,255,255,.15);padding:.4rem 1rem;border-radius:50px;font-size:.85rem}
  .manage-body{padding:2rem}
  .manage-body h3{color:var(--primary-dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block}
  .actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-top:1.5rem}
  .action-btn{background:#fff;border:2px solid #ddd;border-radius:10px;padding:1.3rem 1rem;text-align:center;cursor:pointer;transition:.25s;display:flex;flex-direction:column;align-items:center;gap:.5rem;font-weight:600;color:#444;text-decoration:none;font-family:inherit;font-size:.95rem}
  .action-btn:hover{border-color:var(--primary);background:#e8f5ef;color:var(--primary-dark);transform:translateY(-2px)}
  .action-btn.danger:hover{border-color:#c0392b;background:#fdecea;color:#c0392b}
  .action-btn .ico{font-size:1.8rem}
  .action-btn small{color:#888;font-weight:400;font-size:.78rem}

  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}

  .status-banner{padding:1rem 1.5rem;border-radius:10px;margin-bottom:1.5rem;text-align:center;font-weight:600}
  .status-banner.active{background:#e8f5ef;color:#1d4ed8}
  .status-banner.paused{background:#fef7e0;color:#5b4a2c}
  .status-banner.cancelled{background:#f1f1f1;color:#666}

  .history-row{padding:.8rem 0;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
  .history-row:last-child{border-bottom:none}
  .history-row .left small{color:#888;font-size:.82rem}
  .history-row .amt{color:var(--primary-dark);font-weight:700}

  details summary{cursor:pointer;padding:.7rem 0;color:var(--primary);font-weight:600}
  details summary:hover{color:var(--accent)}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<div class="manage-wrap">
  <?php if ($action_msg): ?>
    <div class="alert <?= e($action_type) ?>"><?= e($action_msg) ?></div>
  <?php endif; ?>

  <div class="manage-card">
    <div class="manage-hero">
      <p style="margin:0;opacity:.9;font-size:.85rem;letter-spacing:2px;text-transform:uppercase">Your Recurring Donation</p>
      <div class="amount"><?= $sym . number_format($r['amount'], 0) ?></div>
      <div class="freq">per <?= e(rtrim($r['frequency'], 'ly')) ?><?= $r['frequency'] === 'weekly' ? 'week' : ($r['frequency'] === 'monthly' ? 'month' : ($r['frequency'] === 'quarterly' ? 'quarter' : 'year')) ?></div>
      <div class="meta-pills">
        <span class="meta-pill">🎯 <?= e($r['purpose']) ?></span>
        <span class="meta-pill">✓ <strong><?= (int)$r['total_cycles'] ?></strong> cycles</span>
        <span class="meta-pill">💝 Total <strong><?= $sym . number_format($r['total_raised'], 0) ?></strong></span>
      </div>
    </div>

    <div class="manage-body">
      <div class="status-banner <?= e($r['status']) ?>">
        <?php if ($r['status'] === 'active'): ?>
          ✓ Your donation is <strong>active</strong>. Next charge: <strong><?= e($r['next_charge_date']) ?></strong>
        <?php elseif ($r['status'] === 'paused'): ?>
          ⏸ Your donation is <strong>paused</strong>. You can resume it any time below.
        <?php elseif ($r['status'] === 'cancelled'): ?>
          ⛔ Your donation has been <strong>cancelled</strong>. Thank you for your past support!
        <?php elseif ($r['status'] === 'expired'): ?>
          ⏰ Your donation has <strong>completed all cycles</strong>. Thank you for your generosity!
        <?php endif; ?>
      </div>

      <h3>Hi <?= e($r['donor_name']) ?>! 👋</h3>
      <p style="color:#555;margin-bottom:1.5rem">
        Thank you for your ongoing support of <strong>Sharan Foundation</strong>. Through your <?= e($r['frequency']) ?> recurring gift,
        you have made a lasting impact on the lives we serve. You can manage your subscription using the options below.
      </p>

      <!-- ACTIONS -->
      <div class="actions-grid">
        <?php if ($r['status'] === 'active'): ?>
          <form method="post" onsubmit="return confirm('Pause your recurring donation? You can resume any time.')">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="do" value="pause">
            <button class="action-btn" style="width:100%;border:2px solid #d4a017">
              <span class="ico">⏸</span>
              Pause
              <small>Skip future charges temporarily</small>
            </button>
          </form>

        <?php elseif ($r['status'] === 'paused'): ?>
          <form method="post">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="do" value="resume">
            <button class="action-btn" style="width:100%;border:2px solid var(--primary)">
              <span class="ico">▶</span>
              Resume
              <small>Restart your recurring gift</small>
            </button>
          </form>
        <?php endif; ?>

        <?php if (in_array($r['status'], ['active','paused'])): ?>
          <button type="button" class="action-btn" onclick="document.getElementById('updateForm').scrollIntoView({behavior:'smooth'})">
            <span class="ico">💰</span>
            Change Amount
            <small>Update your gift size</small>
          </button>

          <button type="button" class="action-btn danger" onclick="document.getElementById('cancelForm').scrollIntoView({behavior:'smooth'})">
            <span class="ico">⛔</span>
            Cancel
            <small>End your subscription</small>
          </button>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>pages/donate.php" class="action-btn" style="border-color:var(--accent)">
          <span class="ico">➕</span>
          Make a One-Time Gift
          <small>In addition to your recurring</small>
        </a>
      </div>

      <!-- UPDATE AMOUNT FORM -->
      <?php if (in_array($r['status'], ['active','paused'])): ?>
      <details id="updateForm" style="margin-top:2rem;background:#f9f9f5;padding:1rem 1.5rem;border-radius:10px">
        <summary>💰 Change donation amount</summary>
        <form method="post" style="margin-top:1rem;display:flex;gap:.7rem;align-items:flex-end;flex-wrap:wrap">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="do" value="update_amount">
          <div style="flex:1;min-width:200px">
            <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.3rem;color:#444">New amount (<?= e($sym) ?>)</label>
            <input type="number" name="new_amount" value="<?= e($r['amount']) ?>" min="1" step="any" required
                   style="width:100%;padding:.7rem 1rem;border:1px solid #ddd;border-radius:8px;font-size:1rem">
          </div>
          <button class="btn btn-primary" style="padding:.7rem 1.4rem">Update Amount</button>
        </form>
      </details>

      <details id="cancelForm" style="margin-top:1rem;background:#fff8f0;padding:1rem 1.5rem;border-radius:10px;border-left:3px solid #c0392b">
        <summary style="color:#c0392b">⛔ Cancel my recurring donation</summary>
        <form method="post" onsubmit="return confirm('Are you sure you want to cancel? You can always start a new one later.')" style="margin-top:1rem">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <input type="hidden" name="do" value="cancel">
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:.3rem;color:#444">Reason (optional, helps us improve)</label>
          <textarea name="reason" rows="3" placeholder="Why are you cancelling?"
                    style="width:100%;padding:.7rem 1rem;border:1px solid #ddd;border-radius:8px;font-family:inherit;margin-bottom:.7rem"></textarea>
          <button class="btn" style="background:#c0392b;color:#fff;padding:.7rem 1.4rem">Confirm Cancellation</button>
        </form>
      </details>
      <?php endif; ?>
    </div>
  </div>

  <!-- CHARGE HISTORY -->
  <?php if ($charges): ?>
  <div class="manage-card">
    <div class="manage-body">
      <h3>📜 Your Recent Charges</h3>
      <div style="margin-top:1rem">
        <?php foreach ($charges as $c): ?>
          <div class="history-row">
            <div class="left">
              <strong><?= e(date('M j, Y', strtotime($c['charged_at']))) ?></strong>
              <br><small><?= e($c['status'] === 'success' ? '✓ Processed' : ($c['status'] === 'manual' ? '📨 Due notice sent' : ucfirst($c['status']))) ?></small>
            </div>
            <div class="amt"><?= $sym . number_format($c['amount'], 0) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div style="text-align:center;color:var(--gray);font-size:.85rem;margin-top:2rem">
    <p>Questions? <a href="<?= BASE_URL ?>pages/contact.php" style="color:var(--primary)">Contact us</a> — we're always happy to help. 💚</p>
    <p style="font-size:.75rem;color:#aaa;margin-top:1rem">
      🔒 This page is private. Bookmark this URL — anyone with the link can manage your subscription.
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
