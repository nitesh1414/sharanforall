<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: ' . BASE_URL . 'pages/fundraisers.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM fundraisers WHERE slug=? LIMIT 1");
$stmt->execute([$slug]);
$f = $stmt->fetch();

if (!$f || !in_array($f['status'], ['active','completed','closed'])) {
    http_response_code(404);
    $page_title = 'Fundraiser Not Found';
    $current_page = 'fundraisers';
    require __DIR__ . '/../includes/public_header.php';
    echo '<section style="padding:5rem 1rem;text-align:center"><h2>🎗️ Fundraiser Not Found</h2><p style="color:var(--gray);margin:1rem 0">This fundraiser doesn\'t exist or is no longer active.</p><a href="' . BASE_URL . 'pages/fundraisers.php" class="btn btn-primary">← Browse All Fundraisers</a></section>';
    require __DIR__ . '/../includes/public_footer.php';
    exit;
}

$contribs = $pdo->prepare("SELECT * FROM fundraiser_contributions WHERE fundraiser_id=? ORDER BY contributed_at DESC LIMIT 20");
$contribs->execute([$f['id']]);
$contribs = $contribs->fetchAll();

$completed_count = (int)$pdo->prepare("SELECT COUNT(*) FROM fundraiser_contributions WHERE fundraiser_id=? AND payment_status='completed'")
                          ->execute([$f['id']]) ?: 0;
$completed_count = (int)$pdo->query("SELECT COUNT(*) FROM fundraiser_contributions WHERE fundraiser_id={$f['id']} AND payment_status='completed'")->fetchColumn();

$sym = $f['currency']==='INR'?'₹':($f['currency']==='GBP'?'£':'$');
$pct = $f['goal_amount'] > 0 ? min(100, round(($f['raised_amount']/$f['goal_amount'])*100)) : 0;
$has_img = !empty($f['cover_image']) && file_exists(__DIR__ . '/../' . $f['cover_image']);

$page_title = $f['title'];
$page_desc = mb_strimwidth(strip_tags($f['story']), 0, 160, '...');
$current_page = 'fundraisers';

$extra_head = '<style>
  .fr-hero{position:relative;height:50vh;min-height:380px;color:#fff;display:flex;align-items:flex-end;
    background:linear-gradient(rgba(29,78,216,.3),rgba(26,46,53,.85)),'.($has_img?"url('".BASE_URL.e($f['cover_image'])."') center/cover":'linear-gradient(135deg,#2563eb,#1d4ed8)').'}
  .fr-hero .container{padding-bottom:3rem;position:relative;z-index:2}
  .fr-hero h1{font-size:clamp(1.8rem,4vw,2.8rem);font-weight:800;line-height:1.2;margin:.6rem 0 .8rem;max-width:900px}
  .fr-hero .badge{display:inline-block;background:var(--accent);padding:.4rem 1rem;border-radius:50px;font-size:.85rem;font-weight:600}

  .layout{display:grid;grid-template-columns:2fr 1fr;gap:2rem;margin-top:-4rem;position:relative;z-index:3}
  .main-col{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:2.5rem}
  .organizer-box{display:flex;align-items:center;gap:1rem;padding-bottom:1.5rem;border-bottom:1px solid #eee;margin-bottom:1.5rem}
  .org-avatar{width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;display:grid;place-items:center;font-weight:700;font-size:1.4rem;flex-shrink:0}
  .organizer-box h4{color:var(--dark);margin-bottom:.2rem;font-size:1.05rem}
  .organizer-box .meta{color:var(--gray);font-size:.85rem}
  .story{line-height:1.8;color:#444}
  .story p{margin-bottom:1rem}

  .side-col{display:flex;flex-direction:column;gap:1.5rem}
  .progress-card{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:2rem;position:sticky;top:90px}
  .progress-card .amount{font-size:2.5rem;color:var(--primary-dark);font-weight:800;margin-bottom:.2rem}
  .progress-card .goal{color:var(--gray);font-size:.95rem;margin-bottom:1rem}
  .progress-card .bar{height:12px;background:#eee;border-radius:50px;overflow:hidden;margin-bottom:.8rem}
  .progress-card .fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:50px;transition:width 1s}
  .progress-card .pct{font-weight:700;color:var(--primary);font-size:1.1rem;margin-bottom:.4rem}
  .progress-card .stats{display:flex;justify-content:space-between;font-size:.85rem;color:var(--gray);padding-top:1rem;border-top:1px solid #eee;margin-top:1rem}

  .contribute-form{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:2rem}
  .contribute-form h3{color:var(--primary-dark);margin-bottom:1rem}
  .quick-amounts{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:1rem}
  .qa-btn{padding:.7rem;border:2px solid #ddd;background:#fff;border-radius:8px;cursor:pointer;font-weight:600;color:#555;transition:.2s}
  .qa-btn:hover,.qa-btn.active{background:var(--primary);color:#fff;border-color:var(--primary)}
  .contribute-form input,.contribute-form textarea{width:100%;padding:.7rem;border:1px solid #ddd;border-radius:6px;margin-bottom:.6rem;font-family:inherit;font-size:.92rem}
  .contribute-form label{display:flex;align-items:center;gap:.4rem;font-size:.85rem;color:#555;margin-bottom:.5rem;cursor:pointer}
  .contribute-form button[type=submit]{width:100%;padding:.95rem;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:1rem;letter-spacing:.5px;transition:.2s}
  .contribute-form button[type=submit]:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(231,111,81,.35)}

  .contribs-card{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:1.5rem}
  .contribs-card h3{color:var(--primary-dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block}
  .contrib-row{padding:.8rem 0;border-bottom:1px solid #f0f0f0;display:flex;gap:.7rem;align-items:flex-start}
  .contrib-row:last-child{border-bottom:none}
  .contrib-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;display:grid;place-items:center;font-weight:700;font-size:.78rem;flex-shrink:0}
  .contrib-row h5{color:var(--dark);font-size:.92rem;margin:0}
  .contrib-row .amt{color:var(--primary-dark);font-weight:700;font-size:.92rem}
  .contrib-row p{font-size:.83rem;color:#666;margin:.2rem 0 0;font-style:italic}
  .contrib-row small{color:var(--gray);font-size:.75rem}

  .share-bar{padding:1.2rem;background:#f9f9f5;border-radius:10px;margin-top:1.5rem;text-align:center}
  .share-btns{display:flex;justify-content:center;gap:.5rem;margin-top:.5rem}
  .share-btns a{width:38px;height:38px;border-radius:50%;background:var(--primary);color:#fff;display:grid;place-items:center;text-decoration:none;font-weight:bold;transition:.2s}
  .share-btns a:hover{background:var(--accent);transform:translateY(-2px)}

  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}

  @media(max-width:880px){
    .layout{grid-template-columns:1fr;margin-top:-2rem}
    .progress-card{position:static}
    .main-col{padding:1.5rem}
  }
</style>';

require __DIR__ . '/../includes/public_header.php';

$share_url = (isset($_SERVER['HTTPS'])?'https':'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>

<section class="fr-hero">
  <div class="container">
    <span class="badge"><?= e($f['cause']) ?></span>
    <h1><?= e($f['title']) ?></h1>
    <p style="opacity:.9;font-size:1rem">Organized by <strong><?= e($f['organizer_name']) ?></strong></p>
  </div>
</section>

<section class="sec-cream" style="padding:0 0 5rem">
  <div class="container">

    <?php if ($flash): ?>
      <div class="alert <?= $flash['type']==='success'?'success':'error' ?>" style="margin-top:2rem"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="layout">
      <!-- MAIN STORY -->
      <div class="main-col">
        <div class="organizer-box">
          <div class="org-avatar"><?= strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$f['organizer_name']),0,2)) ?></div>
          <div>
            <h4><?= e($f['organizer_name']) ?> is fundraising for Sharan Foundation</h4>
            <div class="meta"><?= e($f['organizer_bio'] ?: 'Supporting the cause: ' . $f['cause']) ?></div>
          </div>
        </div>

        <h2 style="color:var(--primary-dark);margin-bottom:1rem">The Story</h2>
        <div class="story"><?= nl2br(e($f['story'])) ?></div>

        <div class="share-bar">
          <strong>📢 Help spread the word:</strong>
          <div class="share-btns">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($share_url) ?>" target="_blank" title="Facebook">f</a>
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($share_url) ?>&text=<?= urlencode($f['title']) ?>" target="_blank" title="Twitter">✕</a>
            <a href="https://wa.me/?text=<?= urlencode($f['title'].' '.$share_url) ?>" target="_blank" title="WhatsApp">w</a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($share_url) ?>" target="_blank" title="LinkedIn">in</a>
            <a href="mailto:?subject=<?= urlencode($f['title']) ?>&body=<?= urlencode($share_url) ?>" title="Email">✉</a>
          </div>
        </div>
      </div>

      <!-- SIDEBAR -->
      <div class="side-col">
        <!-- Progress -->
        <div class="progress-card">
          <div class="amount"><?= $sym . number_format($f['raised_amount'], 0) ?></div>
          <div class="goal">raised of <strong><?= $sym . number_format($f['goal_amount'], 0) ?></strong> goal</div>
          <div class="bar"><div class="fill" style="width:<?= $pct ?>%"></div></div>
          <div class="pct"><?= $pct ?>% funded</div>
          <div class="stats">
            <span><strong><?= count($contribs) ?></strong> contributions</span>
            <?php if ($f['end_date']):
              $days_left = (new DateTime())->diff(new DateTime($f['end_date']))->days;
              $is_past = (new DateTime() > new DateTime($f['end_date'])); ?>
              <span><?= $is_past ? 'ended' : "<strong>$days_left</strong> days left" ?></span>
            <?php endif; ?>
          </div>
          <?php if ($f['status'] === 'active'): ?>
            <a href="#contribute" class="btn btn-primary" style="width:100%;margin-top:1.2rem;padding:.9rem;text-align:center;display:block">💝 Contribute Now</a>
          <?php else: ?>
            <div style="margin-top:1.2rem;padding:.8rem;background:#f0f0f0;color:#666;border-radius:8px;text-align:center;font-size:.9rem">This fundraiser has ended</div>
          <?php endif; ?>
        </div>

        <!-- Contribute Form -->
        <?php if ($f['status'] === 'active'): ?>
        <form id="contribute" action="<?= BASE_URL ?>api/submit_contribution.php" method="post" class="contribute-form">
          <input type="hidden" name="fundraiser_id" value="<?= (int)$f['id'] ?>">
          <h3>💝 Make a Contribution</h3>
          <p style="font-size:.85rem;color:var(--gray);margin-bottom:1rem">All contributions go directly to Sharan Foundation for: <strong><?= e($f['cause']) ?></strong></p>

          <div class="quick-amounts">
            <?php foreach (($f['currency']==='INR'?[500,1000,2500]:[10,25,50]) as $amt): ?>
              <button type="button" class="qa-btn" data-amt="<?= $amt ?>"><?= $sym ?><?= number_format($amt) ?></button>
            <?php endforeach; ?>
          </div>
          <input type="number" name="amount" id="contribAmount" placeholder="<?= $sym ?> Custom amount" min="1" required>
          <input type="text" name="donor_name" placeholder="Your Name *" required>
          <input type="email" name="email" placeholder="Email (for receipt)">
          <textarea name="message" placeholder="Message of support (optional)" rows="2"></textarea>
          <label><input type="checkbox" name="is_anonymous" value="1"> Donate anonymously</label>
          <button type="submit">💝 Contribute <?= $sym ?> →</button>
          <p style="font-size:.75rem;color:var(--gray);text-align:center;margin-top:.5rem">You'll receive bank/UPI details to complete payment.</p>
        </form>
        <?php endif; ?>

        <!-- Recent Contributions -->
        <div class="contribs-card">
          <h3>Recent Supporters</h3>
          <?php if (!$contribs): ?>
            <p style="color:var(--gray);font-size:.88rem;padding:1rem 0">Be the first to contribute! 💝</p>
          <?php else: foreach ($contribs as $c):
            $cn = $c['is_anonymous'] || empty($c['donor_name']) ? 'Anonymous' : $c['donor_name']; ?>
            <div class="contrib-row">
              <div class="contrib-avatar"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$cn),0,2))) ?></div>
              <div style="flex:1">
                <div style="display:flex;justify-content:space-between;gap:.5rem">
                  <h5><?= e($cn) ?></h5>
                  <span class="amt"><?= $sym . number_format($c['amount'], 0) ?></span>
                </div>
                <?php if ($c['message']): ?><p>"<?= e($c['message']) ?>"</p><?php endif; ?>
                <small><?= time_ago($c['contributed_at']) ?></small>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.querySelectorAll('.qa-btn').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.qa-btn').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    document.getElementById('contribAmount').value = b.dataset.amt;
  });
});
</script>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
