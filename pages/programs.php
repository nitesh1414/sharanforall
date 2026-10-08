<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$programs = $pdo->query("SELECT * FROM programs WHERE status='active' ORDER BY display_order, id")->fetchAll();

// Skill development courses, grouped by category (v9)
$courses_by_cat = [];
try {
    $course_rows = $pdo->query("SELECT * FROM program_courses WHERE status='active' ORDER BY category, display_order, id")->fetchAll();
    foreach ($course_rows as $c) { $courses_by_cat[$c['category']][] = $c; }
} catch (Throwable $e) {}

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_programs');
$page_desc  = 'Explore the core programs of Sharan Foundation - child education, women empowerment, hostels, old age home, Bible college and more.';
$current_page = 'programs';
$extra_head = '<style>
  .prog-section{padding:3.25rem 0;border-bottom:1px solid rgba(13,41,64,.06)}
  .prog-section:nth-of-type(odd){background:var(--bg-cream)}
  .prog-section:nth-of-type(even){background:var(--bg-sage)}
  .prog-grid{display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center}
  .prog-grid.reverse{direction:rtl}
  .prog-grid.reverse > *{direction:ltr}
  .prog-img{border-radius:16px;overflow:hidden;box-shadow:var(--shadow);min-height:380px;background-color:#e8eef3;background-size:contain;background-repeat:no-repeat;background-position:center;position:relative}
  .prog-img .badge{position:absolute;top:1.2rem;left:1.2rem;background:var(--accent);color:#fff;padding:.4rem 1rem;border-radius:50px;font-size:.85rem;font-weight:600}
  .prog-content h2{font-size:16px;color:var(--dark);margin-bottom:.5rem}
  .prog-content h2 span{color:var(--primary)}
  .prog-content .sub{color:var(--primary);font-weight:600;margin-bottom:.7rem;font-size:14px;letter-spacing:.6px;text-transform:uppercase}
  .prog-content p{color:var(--ink-muted);margin-bottom:.85rem;font-size:14px}
  .prog-features{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-top:1.2rem}
  .prog-features div{display:flex;align-items:center;gap:.5rem;font-size:14px;color:var(--ink)}
  .prog-features div::before{content:"\\2713";color:var(--primary);font-weight:bold;background:rgba(37,99,235,.1);width:22px;height:22px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;font-size:.75rem}
  .prog-stats{display:flex;gap:2rem;margin:1.5rem 0;flex-wrap:wrap}
  .prog-stats div{text-align:center}
  .prog-stats h4{color:var(--accent);font-size:16px;font-weight:800}
  .prog-stats p{font-size:14px;color:var(--gray);text-transform:uppercase;letter-spacing:.6px;margin:0}
  .placeholder-img{background:linear-gradient(135deg,#1a4d6e,#5fa8c9);display:grid;place-items:center;min-height:380px}
  .placeholder-img.alt{background:linear-gradient(135deg,#5b3a1f,#d4a017)}
  .placeholder-img .icon{font-size:7rem;color:#fff;opacity:.9}
  @media(max-width:880px){
    .prog-grid,.prog-grid.reverse{grid-template-columns:1fr;direction:ltr}
    .prog-features{grid-template-columns:1fr}
    .prog-img,.placeholder-img{min-height:220px}
  }
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<!-- PAGE HEADER -->
<section class="page-header" style="<?= e(site_bg_attr('banner_programs', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_programs')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_programs')) ?></div>
  </div>
</section>

<section class="sec-mist" style="text-align:center">
  <div class="container">
    <span class="tag"><?= e(t('programs_badge')) ?></span>
    <h2 style="color:var(--dark);margin:.8rem 0"><?= e(t('programs_title')) ?></h2>
  </div>
</section>

<?php if (!$programs): ?>
  <section style="padding:5rem 1rem;text-align:center">
    <p style="color:var(--gray)">No programs available yet. Please check back soon.</p>
  </section>
<?php else: ?>

  <?php foreach ($programs as $i => $p):
    $reverse = ($i % 2) === 1;
    $features = $p['features'] ? array_filter(explode('|', $p['features'])) : [];
    $stats    = $p['stats']    ? array_filter(explode('|', $p['stats']))    : [];
    $has_img  = !empty($p['image']) && file_exists(__DIR__ . '/../' . $p['image']);
  ?>
  <section class="prog-section" id="<?= e($p['slug']) ?>">
    <div class="container">
      <div class="prog-grid <?= $reverse ? 'reverse' : '' ?>">
        <?php if ($has_img): ?>
          <div class="prog-img" style="background-image:url('<?= BASE_URL . e($p['image']) ?>')">
            <span class="badge"><?= e($p['icon']) ?> Program <?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></span>
          </div>
        <?php else: ?>
          <div class="prog-img placeholder-img <?= $i % 2 ? 'alt' : '' ?>">
            <div class="icon"><?= e($p['icon'] ?: '📌') ?></div>
            <span class="badge"><?= e($p['icon']) ?> Program <?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></span>
          </div>
        <?php endif; ?>

        <div class="prog-content">
          <?php $sub = tr_field($p, 'subtitle'); if ($sub): ?><div class="sub"><?= e($sub) ?></div><?php endif; ?>
          <h2><?= e(tr_field($p, 'title')) ?></h2>
          <p><?= nl2br(e(tr_field($p, 'long_desc') ?: tr_field($p, 'short_desc'))) ?></p>

          <?php if ($stats): ?>
            <div class="prog-stats">
              <?php foreach ($stats as $st): list($val,$lbl) = array_pad(explode(':', $st, 2), 2, ''); ?>
                <div><h4><?= e($val) ?></h4><p><?= e($lbl) ?></p></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($features): ?>
            <div class="prog-features">
              <?php foreach ($features as $f): ?><div><?= e($f) ?></div><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

<?php endif; ?>

<?php if ($courses_by_cat): ?>
<!-- COURSES / SKILL DEVELOPMENT -->
<section class="sec-lilac">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ COURSES &amp; SKILL DEVELOPMENT</span>
      <h2>Practical Training That <span>Empowers</span></h2>
      <p>Hands-on courses across <strong><?= count($courses_by_cat) ?></strong> categories, designed to build employable skills, character &amp; ministry capacity.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem">
      <?php foreach ($courses_by_cat as $cat => $courses):
        $cat_label = current_lang()==='hi' && !empty($courses[0]['category_hi']) ? $courses[0]['category_hi'] : $cat; ?>
        <div style="background:#fff;border-radius:14px;padding:1.8rem;box-shadow:var(--shadow);border-top:4px solid var(--primary);transition:.25s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
          <h3 style="color:var(--primary-dark);margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block">📂 <?= e($cat_label) ?></h3>
          <ul style="list-style:none;padding:0;margin:.5rem 0">
            <?php foreach ($courses as $c):
              $cn = current_lang()==='hi' && $c['course_name_hi'] ? $c['course_name_hi'] : $c['course_name']; ?>
              <li style="padding:.55rem 0;border-bottom:1px dashed #eee;display:flex;align-items:center;gap:.6rem;color:#444">
                <span style="font-size:1.1rem;flex-shrink:0"><?= e($c['icon']) ?></span>
                <span><?= e($cn) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>

    <?php $motto = current_lang()==='hi' && get_setting('motto_hi') ? get_setting('motto_hi') : get_setting('motto'); ?>
    <?php if ($motto): ?>
      <div style="text-align:center;margin-top:3rem;padding:2rem;background:#fff;border-radius:14px;border:2px dashed var(--accent);max-width:760px;margin-left:auto;margin-right:auto">
        <div style="color:var(--gray);letter-spacing:2px;margin-bottom:.5rem">OUR MOTTO</div>
        <div style="font-weight:700;color:var(--primary-dark);letter-spacing:.5px;font-size:16px">✦ <?= e($motto) ?> ✦</div>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section style="background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;text-align:center;padding:4rem 1rem">
  <h2 style="margin-bottom:.7rem;color:#fff">Support a Program You Believe In</h2>
  <p style="max-width:600px;margin:0 auto 2rem">Pick a cause close to your heart — sponsor a child, fund a hostel bed, or support a Bible college student.</p>
  <a href="<?= BASE_URL ?>pages/donate.php" class="btn" style="background:#fff;color:var(--accent-dark)">Donate Now ♥</a> &nbsp;
  <a href="<?= BASE_URL ?>pages/contact.php" class="btn btn-outline">Contact Us</a>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
