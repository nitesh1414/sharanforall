<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$fundraisers = $pdo->query("
    SELECT f.*, (SELECT COUNT(*) FROM fundraiser_contributions WHERE fundraiser_id=f.id AND payment_status='completed') AS contribs
    FROM fundraisers f
    WHERE status IN ('active','completed')
    ORDER BY is_featured DESC, submitted_at DESC
")->fetchAll();

$total_raised_inr = (float)$pdo->query("SELECT COALESCE(SUM(raised_amount),0) FROM fundraisers WHERE currency='INR'")->fetchColumn();
$total_raised_gbp = (float)$pdo->query("SELECT COALESCE(SUM(raised_amount),0) FROM fundraisers WHERE currency='GBP'")->fetchColumn();
$active_count     = (int)$pdo->query("SELECT COUNT(*) FROM fundraisers WHERE status='active'")->fetchColumn();

$page_title = 'Peer Fundraisers';
$page_desc = 'Browse community-led fundraising campaigns supporting Sharan Foundation.';
$current_page = 'fundraisers';
$extra_head = '<style>
  .hero{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;text-align:center;padding:4rem 0}
  .hero h1{font-size:clamp(2rem,4vw,2.8rem);font-weight:800;margin-bottom:.6rem}
  .hero p{opacity:.95;max-width:680px;margin:0 auto 1.5rem}
  .hero-stats{display:flex;justify-content:center;gap:2.5rem;flex-wrap:wrap;margin-top:2rem}
  .hero-stats h3{font-size:1.8rem;color:#f4a261;font-weight:800}
  .hero-stats p{font-size:.85rem;opacity:.85;text-transform:uppercase;letter-spacing:1px;margin:0}

  .fr-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:2rem;margin:2rem 0}
  .fr-card{background:#fff;border-radius:14px;box-shadow:var(--shadow);overflow:hidden;transition:.3s;display:flex;flex-direction:column;text-decoration:none;color:inherit}
  .fr-card:hover{transform:translateY(-6px);box-shadow:0 18px 40px rgba(0,0,0,.12)}
  .fr-cover{height:200px;background-size:cover;background-position:center;position:relative;display:grid;place-items:center;color:#fff;font-size:4rem}
  .fr-cover::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 40%,rgba(0,0,0,.5))}
  .fr-cover .badge{position:absolute;top:1rem;left:1rem;background:var(--accent);color:#fff;padding:.3rem .9rem;border-radius:50px;font-size:.78rem;font-weight:600;z-index:2}
  .fr-cover .feat{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.95);color:#d4a017;padding:.3rem .9rem;border-radius:50px;font-size:.78rem;font-weight:700;z-index:2}
  .fr-body{padding:1.5rem;flex:1;display:flex;flex-direction:column}
  .fr-body h3{color:var(--primary-dark);font-size:1.15rem;margin-bottom:.5rem;line-height:1.35}
  .fr-organizer{display:flex;align-items:center;gap:.5rem;margin-bottom:.8rem;font-size:.85rem;color:var(--gray)}
  .fr-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;display:grid;place-items:center;font-weight:700;font-size:.78rem}
  .fr-story{color:var(--gray);font-size:.92rem;margin-bottom:1rem;flex:1;display:-webkit-box;-webkit-line-clamp:3;line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
  .fr-progress-info{display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:.4rem}
  .fr-progress-info strong{color:var(--primary-dark)}
  .fr-progress-bar{height:8px;background:#eee;border-radius:50px;overflow:hidden;margin-bottom:.7rem}
  .fr-progress-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:50px;transition:width 1s ease}
  .fr-meta{display:flex;justify-content:space-between;font-size:.8rem;color:var(--gray);padding-top:.8rem;border-top:1px solid #f0f0f0;margin-top:auto}

  .cta-band{background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;text-align:center;padding:4rem 1rem;border-radius:14px;margin:2rem 0}
  .cta-band h2{font-size:clamp(1.6rem,3vw,2.2rem);margin-bottom:.8rem}
  .cta-band p{opacity:.95;margin-bottom:1.5rem}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <span class="tag" style="background:rgba(244,162,97,.2);color:var(--accent)">✦ COMMUNITY FUNDRAISING</span>
    <h1 style="margin-top:.6rem">People Raising Hope, Together 🎗️</h1>
    <p>Browse fundraising campaigns started by everyday people supporting Sharan Foundation. Every contribution moves the needle on real change.</p>
    <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap;margin-top:1.5rem">
      <a href="<?= BASE_URL ?>pages/start-fundraiser.php" class="btn btn-primary">🚀 Start Your Own Fundraiser</a>
      <a href="<?= BASE_URL ?>pages/donate.php" class="btn btn-outline">💝 Or Donate Directly</a>
    </div>
    <div class="hero-stats">
      <div><h3><?= number_format($active_count) ?></h3><p>Active Campaigns</p></div>
      <div><h3>₹<?= number_format($total_raised_inr, 0) ?></h3><p>Raised in India</p></div>
      <div><h3>£<?= number_format($total_raised_gbp, 0) ?></h3><p>Raised in UK</p></div>
    </div>
  </div>
</section>

<section class="sec-cream">
  <div class="container">
    <?php if ($flash): ?>
      <div class="alert <?= $flash['type']==='success'?'success':'error' ?>" style="padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500;<?= $flash['type']==='success'?'background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb':'background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c' ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="section-head">
      <span class="tag">✦ ACTIVE CAMPAIGNS</span>
      <h2>Fundraisers in <span>Motion</span></h2>
      <p>Join a campaign close to your heart, or share these stories with friends.</p>
    </div>

    <?php if (!$fundraisers): ?>
      <p style="text-align:center;color:var(--gray);padding:3rem 0">No active fundraisers right now. Be the first — <a href="<?= BASE_URL ?>pages/start-fundraiser.php">start one</a>!</p>
    <?php else: ?>
    <div class="fr-grid">
      <?php foreach ($fundraisers as $f):
        $sym = $f['currency']==='INR'?'₹':($f['currency']==='GBP'?'£':'$');
        $pct = $f['goal_amount'] > 0 ? min(100, round(($f['raised_amount']/$f['goal_amount'])*100)) : 0;
        $has_img = !empty($f['cover_image']) && file_exists(__DIR__ . '/../' . $f['cover_image']);
        $initials = strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$f['organizer_name']),0,2)); ?>
      <a href="<?= BASE_URL ?>pages/fundraiser.php?slug=<?= e($f['slug']) ?>" class="fr-card">
        <div class="fr-cover" style="<?= $has_img ? "background-image:url('".BASE_URL.e($f['cover_image'])."')" : 'background:linear-gradient(135deg,#2563eb,#f4a261)' ?>">
          <?php if (!$has_img): ?>🎗️<?php endif; ?>
          <?php if ($f['is_featured']): ?><span class="feat">★ FEATURED</span><?php endif; ?>
          <span class="badge"><?= e($f['cause']) ?></span>
        </div>
        <div class="fr-body">
          <h3><?= e($f['title']) ?></h3>
          <div class="fr-organizer">
            <div class="fr-avatar"><?= e($initials) ?></div>
            <span>by <strong><?= e($f['organizer_name']) ?></strong></span>
          </div>
          <p class="fr-story"><?= e(mb_strimwidth(strip_tags($f['story']), 0, 140, '...')) ?></p>
          <div class="fr-progress-info">
            <span>Raised: <strong><?= $sym . number_format($f['raised_amount'],0) ?></strong></span>
            <span>Goal: <?= $sym . number_format($f['goal_amount'],0) ?></span>
          </div>
          <div class="fr-progress-bar"><div class="fr-progress-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="fr-meta">
            <span><strong style="color:var(--primary)"><?= $pct ?>%</strong> funded</span>
            <span>💝 <?= (int)$f['contribs'] ?> contributions</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="cta-band">
      <h2>Have a Cause Close to Your Heart? 💝</h2>
      <p>Start your own fundraiser today — for a birthday, marathon, anniversary, or simply because you want to make a difference.</p>
      <a href="<?= BASE_URL ?>pages/start-fundraiser.php" class="btn" style="background:#fff;color:var(--accent-dark);padding:1rem 2rem;font-weight:700">🚀 Start a Fundraiser</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
