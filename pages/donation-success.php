<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$donation_id = (int)($_GET['donation_id'] ?? 0);
$donation = null;
if ($donation_id) {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch();
}

$page_title = 'Thank You for Your Donation';
$current_page = 'donate';
$extra_head = '<style>
  .success-wrap{max-width:680px;margin:0 auto;padding:3rem 1rem;text-align:center}
  .success-card{background:#fff;border-radius:18px;box-shadow:var(--shadow);padding:3rem 2rem;animation:popIn .5s ease}
  .success-icon{width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;display:grid;place-items:center;font-size:3rem;margin:0 auto 1.5rem;box-shadow:0 14px 30px rgba(37,99,235,.3);animation:bounce .8s ease}
  .success-card h1{color:var(--primary-dark);font-size:2rem;margin-bottom:.8rem}
  .success-card .amount-block{background:linear-gradient(135deg,#fffaf0,#fff);border:2px dashed var(--accent);padding:1.5rem;border-radius:12px;margin:1.5rem 0}
  .success-card .amount-block .amount{font-size:2.5rem;color:var(--primary);font-weight:800}
  .success-card .amount-block .receipt{font-family:monospace;color:#666;font-size:.9rem;margin-top:.3rem}
  .next-steps{background:#f9f9f5;border-left:4px solid var(--accent);padding:1.2rem 1.5rem;border-radius:8px;text-align:left;margin:1.5rem 0}
  .next-steps h3{color:var(--primary-dark);font-size:1rem;margin-bottom:.7rem}
  .next-steps ol{padding-left:1.2rem;color:#444;font-size:.9rem;line-height:1.8}
  .verse{font-style:italic;color:#5b4a2c;background:#fffaf0;padding:1rem 1.4rem;border-radius:10px;margin:1.5rem 0;font-size:.95rem;border-left:4px solid #d4a017}
  @keyframes popIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
  @keyframes bounce{0%{transform:scale(0)}60%{transform:scale(1.1)}100%{transform:scale(1)}}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<div class="success-wrap">
  <div class="success-card">
    <div class="success-icon">✓</div>
    <h1>Thank You! 🙏</h1>
    <p style="color:#555;font-size:1.05rem">Your generous donation has been received successfully.</p>

    <?php if ($donation): $sym = $donation['currency']==='INR'?'₹':($donation['currency']==='GBP'?'£':'$'); ?>
      <div class="amount-block">
        <div class="amount"><?= $sym . number_format($donation['amount'], 0) ?></div>
        <p style="color:#666;margin-top:.4rem">towards <strong><?= e($donation['purpose']) ?></strong></p>
        <?php if ($donation['receipt_number']): ?>
          <p class="receipt">Receipt: <strong><?= e($donation['receipt_number']) ?></strong></p>
        <?php endif; ?>
      </div>

      <div class="next-steps">
        <h3>📬 What happens next:</h3>
        <ol>
          <li>A confirmation email has been sent to <strong><?= e($donation['email']) ?></strong></li>
          <li>An official PDF tax receipt is attached to that email (80G / Gift Aid eligible)</li>
          <?php if (in_array($donation['donation_type'], ['monthly','yearly'])): ?>
            <li>You'll receive a personal link to manage your recurring donation</li>
            <li>We'll email you 3 days before each upcoming charge as a reminder</li>
          <?php endif; ?>
        </ol>
      </div>
    <?php endif; ?>

    <div class="verse">
      "Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver."<br>
      <strong>— 2 Corinthians 9:7</strong>
    </div>

    <div style="display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;margin-top:1.5rem">
      <a href="<?= BASE_URL ?>" class="btn btn-primary">🏠 Back to Home</a>
      <a href="<?= BASE_URL ?>pages/programs.php" class="btn btn-outline">📚 See Our Programs</a>
    </div>
  </div>

  <p style="color:var(--gray);font-size:.85rem;margin-top:2rem">
    Questions? <a href="<?= BASE_URL ?>pages/contact.php" style="color:var(--primary)">Contact us</a> — we're here to help.
  </p>
</div>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
