<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$images = $pdo->query("SELECT * FROM gallery ORDER BY display_order, id DESC")->fetchAll();

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_gallery');
$page_desc  = 'Browse moments from our schools, hostels, outreaches and events at Sharan Foundation.';
$current_page = 'gallery';
$extra_head = '<style>
  .filters{display:flex;justify-content:center;gap:.7rem;margin-bottom:2.5rem;flex-wrap:wrap}
  .filters button{padding:.55rem 1.3rem;border:2px solid var(--primary);background:transparent;color:var(--primary);border-radius:50px;cursor:pointer;font-weight:600;transition:.25s;font-size:.9rem}
  .filters button:hover,.filters button.active{background:var(--primary);color:#fff}
  .gallery-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.2rem}
  .gallery-item{position:relative;border-radius:12px;overflow:hidden;cursor:pointer;aspect-ratio:4/3;background-size:cover;background-position:center;transition:.3s;box-shadow:var(--shadow)}
  .gallery-item:hover{transform:scale(1.03);box-shadow:0 14px 30px rgba(0,0,0,.2)}
  .gallery-item img{display:none}
  .gallery-item::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 40%,rgba(0,0,0,.75));opacity:0;transition:.3s}
  .gallery-item:hover::before{opacity:1}
  .gallery-overlay{position:absolute;left:0;right:0;bottom:0;padding:1.2rem;color:#fff;transform:translateY(20px);opacity:0;transition:.3s;z-index:2}
  .gallery-item:hover .gallery-overlay{transform:translateY(0);opacity:1}
  .gallery-overlay h4{font-size:1.05rem;margin-bottom:.2rem}
  .gallery-overlay span{font-size:.8rem;opacity:.85}
  .gallery-overlay::after{content:"\\1F50D";position:absolute;top:-2rem;right:1.2rem;background:var(--accent);width:38px;height:38px;border-radius:50%;display:grid;place-items:center;font-size:1rem}
  .lightbox{position:fixed;inset:0;background:rgba(0,0,0,.92);display:grid;place-items:center;z-index:9999;padding:2rem;animation:fade .25s ease}
  .lightbox img{max-width:90vw;max-height:80vh;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
  .lb-close{position:absolute;top:1rem;right:1.5rem;color:#fff;font-size:2.5rem;cursor:pointer;line-height:1}
  .lb-caption{position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);color:#fff;background:rgba(0,0,0,.6);padding:.6rem 1.4rem;border-radius:50px;font-size:.95rem}
  @keyframes fade{from{opacity:0}to{opacity:1}}
</style>';

require __DIR__ . '/../includes/public_header.php';

// Get distinct categories present in DB
$cats_in_db = $pdo->query("SELECT DISTINCT category FROM gallery ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_gallery', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_gallery')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_gallery')) ?></div>
  </div>
</section>

<section class="sec-cream">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ MOMENTS OF GRACE</span>
      <h2>Glimpses of Our <span>Journey</span></h2>
      <p>Photos from our schools, hostels, outreaches and events. Every frame tells a story of hope.</p>
    </div>

    <?php if (count($cats_in_db) > 1): ?>
    <div class="filters">
      <button class="filter-btn active" data-filter="all"><?= e(t('all')) ?></button>
      <?php foreach ($cats_in_db as $c): ?>
        <button class="filter-btn" data-filter="<?= e($c) ?>"><?= ucfirst(e($c)) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$images): ?>
      <p style="text-align:center;color:var(--gray);padding:3rem 0">No images yet. Please check back soon!</p>
    <?php else: ?>
    <div class="gallery-grid">
      <?php foreach ($images as $img):
        $url = BASE_URL . e($img['image']);
        $has_img = !empty($img['image']) && file_exists(__DIR__ . '/../' . $img['image']);
      ?>
        <div class="gallery-item"
             data-category="<?= e($img['category']) ?>"
             data-src="<?= $url ?>"
             data-caption="<?= e(tr_field($img, 'caption')) ?>"
             style="background-image:url('<?= $has_img ? $url : '' ?>');<?= !$has_img ? 'background:linear-gradient(135deg,#2563eb,#f4a261);display:grid;place-items:center;color:#fff;font-size:4rem' : '' ?>">
          <?php if (!$has_img): ?>📷<?php endif; ?>
          <div class="gallery-overlay">
            <h4><?= e(tr_field($img, 'title')) ?></h4>
            <span><?= e(tr_field($img, 'caption')) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- VIDEO HIGHLIGHT -->
<section style="background:#0a1c2e;color:#fff;text-align:center">
  <div class="container">
    <span class="tag" style="background:rgba(244,162,97,.2);color:var(--accent)">✦ WATCH OUR STORY</span>
    <h2 style="color:#fff;margin:.8rem 0">Witness the Impact</h2>
    <p style="opacity:.9;max-width:600px;margin:0 auto 2rem">A short video showcasing the lives being transformed every day at Sharan Foundation.</p>
    <div style="max-width:800px;margin:0 auto;aspect-ratio:16/9;background:linear-gradient(135deg,#1d4ed8,#0d2940);border-radius:14px;display:grid;place-items:center;box-shadow:0 20px 60px rgba(0,0,0,.4);cursor:pointer;position:relative;overflow:hidden">
      <div style="position:absolute;inset:0;background:url('<?= e(site_image_url('home_hero', 'images/hero.jpg')) ?>') center/cover;opacity:.4"></div>
      <div style="position:relative;z-index:2;text-align:center">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--accent);display:grid;place-items:center;font-size:2rem;color:#fff;margin:0 auto 1rem;box-shadow:0 10px 30px rgba(0,0,0,.4)">▶</div>
        <p style="font-weight:600">Watch "Stories of Hope" — 3 min</p>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
