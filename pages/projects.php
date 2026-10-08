<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$projects = $pdo->query("SELECT * FROM projects ORDER BY display_order, id DESC")->fetchAll();

// Aggregate impact numbers
$total_projects = count($projects);
$completed      = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='completed'")->fetchColumn();
$active         = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('active','urgent','seasonal')")->fetchColumn();
$total_raised   = 0;
foreach ($projects as $p) {
    // Normalize to a rough GBP estimate (1 GBP ≈ 100 INR)
    $total_raised += $p['currency'] === 'INR' ? $p['raised_amount'] / 100 : $p['raised_amount'];
}

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_projects');
$page_desc  = 'Active and ongoing projects of Sharan Foundation - help fund hostels, schools, shelters and outreach campaigns.';
$current_page = 'projects';
$extra_head = '<style>
  .filters{display:flex;justify-content:center;gap:.7rem;margin-bottom:2.5rem;flex-wrap:wrap}
  .filters button{padding:.55rem 1.3rem;border:2px solid var(--primary);background:transparent;color:var(--primary);border-radius:50px;cursor:pointer;font-weight:600;transition:.25s;font-size:.9rem}
  .filters button:hover,.filters button.active{background:var(--primary);color:#fff}
  .projects-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:2rem}
  .proj{background:#fff;border-radius:14px;box-shadow:var(--shadow);overflow:hidden;transition:.3s;display:flex;flex-direction:column}
  .proj:hover{transform:translateY(-8px)}
  .proj-img{height:220px;background-color:#e8eef3;background-size:contain;background-repeat:no-repeat;background-position:center;position:relative}
  .proj-img::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 50%,rgba(0,0,0,.5))}
  .proj-cat{position:absolute;top:1rem;left:1rem;background:var(--accent);color:#fff;padding:.3rem .9rem;border-radius:50px;font-size:.78rem;font-weight:600;z-index:2;text-transform:capitalize}
  .proj-status{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.95);color:var(--primary-dark);padding:.3rem .9rem;border-radius:50px;font-size:.78rem;font-weight:600;z-index:2;text-transform:capitalize}
  .proj-status.complete{background:var(--primary);color:#fff}
  .proj-status.urgent{background:#e74c3c;color:#fff}
  .proj-loc{position:absolute;bottom:.8rem;left:1rem;color:#fff;font-size:.85rem;z-index:2}
  .proj-body{padding:1.5rem;flex:1;display:flex;flex-direction:column}
  .proj-body h3{color:var(--primary-dark);font-size:1.2rem;margin-bottom:.5rem}
  .proj-body p{color:var(--gray);font-size:.92rem;margin-bottom:1rem;flex:1}
  .progress-info{display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.4rem;color:#444}
  .progress-info strong{color:var(--primary-dark)}
  .progress-bar{height:8px;background:#eee;border-radius:50px;overflow:hidden;margin-bottom:1rem}
  .progress-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:50px;transition:width 1s ease}
  .proj-meta{display:flex;justify-content:space-between;align-items:center;padding-top:1rem;border-top:1px solid #f0f0f0;font-size:.85rem;color:var(--gray)}
  .proj-meta .btn{padding:.5rem 1rem;font-size:.85rem}
  .placeholder-img{display:grid;place-items:center;height:220px}
  .placeholder-img .icon{font-size:5rem;color:#fff;opacity:.9}
  .impact{background:var(--primary-dark);color:#fff;padding:4rem 0;text-align:center}
  .impact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:2rem;margin-top:2rem}
  .impact h2{color:#fff;font-size:2rem;margin-bottom:.5rem}
  .impact-stat h3{color:var(--accent);font-size:2.4rem;font-weight:800}
  .impact-stat p{opacity:.9;text-transform:uppercase;letter-spacing:1px;font-size:.85rem}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_projects', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_projects')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_projects')) ?></div>
  </div>
</section>

<section class="sec-mist">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ ONGOING INITIATIVES</span>
      <h2>Projects in <span>Action</span></h2>
      <p>Be part of the change — every project here is an opportunity to make a real impact.</p>
    </div>

    <div class="filters">
      <button class="filter-btn active" data-filter="all">All Projects</button>
      <button class="filter-btn" data-filter="education">Education</button>
      <button class="filter-btn" data-filter="shelter">Shelter</button>
      <button class="filter-btn" data-filter="infrastructure">Infrastructure</button>
      <button class="filter-btn" data-filter="outreach">Outreach</button>
    </div>

    <?php if (!$projects): ?>
      <p style="text-align:center;color:var(--gray);padding:3rem 0">No projects yet. Please check back soon.</p>
    <?php else: ?>
    <div class="projects-grid">
      <?php
      $placeholders = [
        'education' => ['icon'=>'📚','bg'=>'linear-gradient(135deg,#2563eb,#f4a261)'],
        'shelter' => ['icon'=>'🏠','bg'=>'linear-gradient(135deg,#5b3a1f,#d4a017)'],
        'infrastructure' => ['icon'=>'🏗️','bg'=>'linear-gradient(135deg,#1a4d6e,#5fa8c9)'],
        'outreach' => ['icon'=>'🎄','bg'=>'linear-gradient(135deg,#e76f51,#f4a261)'],
      ];
      foreach ($projects as $p):
        $pct = $p['goal_amount'] > 0 ? min(100, round(($p['raised_amount']/$p['goal_amount'])*100)) : 0;
        $has_img = !empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image']);
        $ph = $placeholders[$p['category']] ?? ['icon'=>'📌','bg'=>'#2563eb'];
      ?>
      <div class="proj gallery-item" data-category="<?= e($p['category']) ?>">
        <?php if ($has_img): ?>
          <div class="proj-img" style="background-image:url('<?= BASE_URL . e($p['image']) ?>')">
        <?php else: ?>
          <div class="proj-img placeholder-img" style="background:<?= $ph['bg'] ?>">
            <div class="icon"><?= $ph['icon'] ?></div>
        <?php endif; ?>
          <span class="proj-cat"><?= e($p['category']) ?></span>
          <span class="proj-status <?= $p['status']==='completed'?'complete':($p['status']==='urgent'?'urgent':'') ?>"><?= e($p['status']) ?></span>
          <?php if ($p['location']): ?><span class="proj-loc">📍 <?= e($p['location']) ?></span><?php endif; ?>
        </div>
        <div class="proj-body">
          <h3><?= e(tr_field($p, 'title')) ?></h3>
          <p><?= e(tr_field($p, 'description')) ?></p>
          <div class="progress-info">
            <span><?= e(t('raised_label')) ?>: <strong><?= format_money($p['raised_amount'], $p['currency']) ?></strong></span>
            <span><?= e(t('goal_label')) ?>: <?= format_money($p['goal_amount'], $p['currency']) ?></span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="proj-meta">
            <span><?= $pct ?>% <?= e(t('funded_label')) ?><?php if ($p['days_left'] > 0): ?> • <?= (int)$p['days_left'] ?> <?= e(t('days_left')) ?><?php endif; ?></span>
            <?php if ($p['status']==='completed'): ?>
              <a href="<?= BASE_URL ?>pages/gallery.php" class="btn btn-secondary"><?= e(t('btn_view_all')) ?></a>
            <?php else: ?>
              <a href="<?= BASE_URL ?>pages/donate.php" class="btn btn-primary"><?= e(t('nav_donate')) ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- IMPACT -->
<section class="impact">
  <div class="container">
    <span class="tag" style="background:rgba(244,162,97,.2);color:var(--accent)">✦ OUR IMPACT</span>
    <h2>Together, We've Achieved So Much</h2>
    <p style="opacity:.9">Every number represents a life touched, a future changed.</p>
    <div class="impact-grid">
      <div class="impact-stat"><h3><?= (int)$completed + 42 ?></h3><p>Projects Completed</p></div>
      <div class="impact-stat"><h3>£<?= number_format($total_raised, 0) ?>+</h3><p>Funds Mobilized</p></div>
      <div class="impact-stat"><h3><?= (int)$active ?></h3><p>Active Projects</p></div>
      <div class="impact-stat"><h3>2,500+</h3><p>Lives Impacted</p></div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
