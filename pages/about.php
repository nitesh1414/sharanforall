<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$team = $pdo->query("SELECT * FROM team_members ORDER BY display_order, id")->fetchAll();

// New story-driven content (with graceful fallbacks if v9 migration not run)
$milestones = []; $phases = []; $vision_cap = [];
try { $milestones = $pdo->query("SELECT * FROM milestones WHERE status='active' ORDER BY display_order, id")->fetchAll(); } catch (Throwable $e) {}
try { $phases     = $pdo->query("SELECT * FROM mission_phases ORDER BY display_order, phase_number")->fetchAll(); } catch (Throwable $e) {}
try { $vision_cap = $pdo->query("SELECT * FROM vision_capacity ORDER BY display_order, id")->fetchAll(); } catch (Throwable $e) {}
$total_vision_capacity = array_sum(array_column($vision_cap, 'capacity'));

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_about');
$page_desc = 'Learn about Sharan Foundation - our story, mission, leadership and impact across India and the UK.';
$current_page = 'about';
$extra_head = '<style>
  .story-grid{display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center}
  .story-img{border-radius:16px;overflow:hidden;box-shadow:var(--shadow);min-height:400px;background:url(\''.BASE_URL.'images/about.jpg\') center/cover}
  .story h2{color:var(--dark);margin-bottom:.7rem;font-size:16px}
  .story h2 span{color:var(--primary)}
  .story p{color:var(--ink-muted);margin-bottom:.85rem;font-size:14px}
  .verse{border-left:4px solid var(--accent);padding:1rem 1.2rem;background:#fffaf0;font-style:italic;color:#5b4a2c;margin:1.5rem 0;border-radius:6px}
  .mv{background:var(--bg-sage)}
  .mv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:2rem}
  .mv-card{background:#fff;padding:2.5rem;border-radius:14px;box-shadow:var(--shadow);text-align:center}
  .mv-card .ico{width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));margin:0 auto 1.2rem;display:grid;place-items:center;color:#fff;font-size:1.8rem}
  .mv-card h3{color:var(--dark);margin-bottom:.8rem}
  .timeline{background:var(--bg-peach);position:relative}
  .timeline-line{position:absolute;left:50%;top:0;bottom:0;width:3px;background:linear-gradient(180deg,var(--primary),var(--accent));transform:translateX(-50%)}
  .tl-item{position:relative;width:50%;padding:1.5rem 2.5rem;margin-bottom:1rem}
  .tl-item:nth-child(odd){left:0;text-align:right}
  .tl-item:nth-child(even){left:50%}
  .tl-item::before{content:"";position:absolute;top:1.8rem;width:18px;height:18px;border-radius:50%;background:var(--accent);border:4px solid #fff;box-shadow:0 0 0 3px var(--primary)}
  .tl-item:nth-child(odd)::before{right:-9px}
  .tl-item:nth-child(even)::before{left:-9px}
  .tl-card{background:#fff;padding:1.5rem;border-radius:12px;box-shadow:var(--shadow);border-top:3px solid var(--accent)}
  .tl-card h4{color:var(--primary-dark);margin-bottom:.4rem}
  .tl-year{display:inline-block;background:var(--primary);color:#fff;padding:.2rem .7rem;border-radius:50px;font-size:.85rem;font-weight:700;margin-bottom:.5rem}
  .team{background:var(--bg-lilac)}
  .team-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:2rem}
  .team-card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:var(--shadow);text-align:center;transition:.3s}
  .team-card:hover{transform:translateY(-6px)}
  .team-avatar{height:220px;background:linear-gradient(135deg,var(--primary),var(--accent));display:grid;place-items:center;color:#fff;font-size:4rem;font-weight:800;background-size:cover;background-position:center}
  .team-body{padding:1.5rem}
  .team-body h4{color:var(--dark);font-size:14px;margin-bottom:.3rem}
  .team-body .role{color:var(--primary);font-size:14px;font-weight:600;margin-bottom:.5rem}
  .team-body p{color:var(--ink-muted);font-size:14px}
  @media(max-width:880px){
    .story-grid{grid-template-columns:1fr}
    .timeline-line{left:18px}
    .tl-item,.tl-item:nth-child(odd),.tl-item:nth-child(even){width:100%;left:0;text-align:left;padding-left:3rem}
    .tl-item::before,.tl-item:nth-child(odd)::before,.tl-item:nth-child(even)::before{left:9px;right:auto}
  }
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_about', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_about')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_about')) ?></div>
  </div>
</section>

<section class="sec-cream">
  <div class="container">
    <div class="story-grid story">
      <div class="story-img" style="<?= e(site_bg_attr('story_img', 'linear-gradient(rgba(13,41,64,.18),rgba(29,78,216,.12))', 'images/about.jpg')) ?>"></div>
      <div>
        <span class="tag">✦ OUR STORY</span>
        <h2>From a Small Group to a <span>Global Mission</span></h2>
        <?php $story = get_setting('story_intro') ?: get_setting('about_short'); if (current_lang() === 'hi' && get_setting('story_intro_hi')) $story = get_setting('story_intro_hi'); ?>
        <p><?= nl2br(e($story)) ?></p>
        <?php $verse = get_setting('guiding_scripture'); $verse_ref = get_setting('scripture_reference'); ?>
        <?php if ($verse): ?>
          <div class="verse">"<?= e($verse) ?>"<?php if ($verse_ref): ?> — <strong><?= e($verse_ref) ?></strong><?php endif; ?></div>
        <?php else: ?>
          <div class="verse">"They sold property and possessions to give to anyone who had need." — <strong>Acts 2:45</strong></div>
        <?php endif; ?>
        <?php $locations = get_setting('locations_served') ?: 'India &amp; the UK'; ?>
        <p>We are dedicated to serving the most vulnerable across <strong><?= e($locations) ?></strong>, accountable to our donors, beneficiaries, and above all, to God.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($vision_cap || get_setting('vision_statement')): ?>
<!-- VISION & CAPACITY -->
<section id="vision" class="sec-mist">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ OUR VISION</span>
      <h2>A <span>Community Care &amp; Transformation</span> Campus</h2>
      <?php if ($total_vision_capacity > 0): ?>
        <p>Serving <strong style="color:var(--primary)"><?= number_format($total_vision_capacity) ?>+</strong> vulnerable individuals through a comprehensive care campus.</p>
      <?php endif; ?>
    </div>

    <?php if ($vision_cap): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-bottom:3rem">
      <?php foreach ($vision_cap as $vc): ?>
        <div style="background:#fff;border-radius:14px;padding:1.8rem;text-align:center;box-shadow:var(--shadow);border-top:4px solid var(--primary);transition:.25s" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
          <div style="font-size:2.2rem;margin-bottom:.6rem"><?= e($vc['icon']) ?></div>
          <div style="font-size:16px;color:var(--primary);font-weight:800"><?= number_format($vc['capacity']) ?></div>
          <p style="color:var(--gray);text-transform:uppercase;letter-spacing:1px;margin-top:.3rem"><?= e(current_lang()==='hi' && $vc['category_hi'] ? $vc['category_hi'] : $vc['category']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php $vs = current_lang()==='hi' && get_setting('vision_statement_hi') ? get_setting('vision_statement_hi') : get_setting('vision_statement'); ?>
    <?php if ($vs): ?>
    <blockquote style="max-width:760px;margin:0 auto;text-align:center;background:#fff;padding:1.5rem 1.8rem;border-radius:14px;border-left:4px solid var(--accent);font-style:italic;color:#444;line-height:1.7;box-shadow:var(--shadow)">
      "<?= e($vs) ?>"
    </blockquote>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($phases): ?>
<!-- MISSION DEVELOPMENT PLAN -->
<section class="sec-sand">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ MISSION DEVELOPMENT PLAN</span>
      <h2>Our <span>Phased Plan</span></h2>
      <p>A step-by-step roadmap to build the Community Care Campus.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem">
      <?php foreach ($phases as $ph):
        $sc = ['upcoming'=>'#d4a017','active'=>'#2563eb','completed'=>'#0b6e4f'][$ph['status']] ?? '#888'; ?>
        <div style="background:#fff;border-radius:14px;padding:1.8rem;box-shadow:var(--shadow);border-top:4px solid <?= $sc ?>;transition:.25s;position:relative" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
          <div style="position:absolute;top:1rem;right:1rem;background:<?= $sc ?>;color:#fff;padding:.2rem .7rem;border-radius:50px;font-size:.7rem;font-weight:700;text-transform:uppercase"><?= e($ph['status']) ?></div>
          <div style="font-size:2.4rem;margin-bottom:.6rem"><?= e($ph['icon']) ?></div>
          <div style="color:var(--primary);font-weight:800;font-size:.85rem;letter-spacing:1px;text-transform:uppercase;margin-bottom:.3rem">Phase <?= (int)$ph['phase_number'] ?></div>
          <h3 style="color:var(--dark);margin-bottom:.6rem"><?= e(current_lang()==='hi' && $ph['title_hi'] ? $ph['title_hi'] : $ph['title']) ?></h3>
          <?php if ($ph['capacity']): ?>
            <span style="display:inline-block;background:#eaf2ff;color:var(--primary-dark);padding:.2rem .7rem;border-radius:50px;font-size:.78rem;font-weight:600;margin-bottom:.7rem">📊 <?= e($ph['capacity']) ?></span>
          <?php endif; ?>
          <p style="color:var(--gray)"><?= e(current_lang()==='hi' && $ph['description_hi'] ? $ph['description_hi'] : $ph['description']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="mv">
  <div class="container">
    <div class="section-head">
      <span class="tag"><?= e(t('mv_badge')) ?></span>
      <h2><?= e(t('mv_title')) ?></h2>
    </div>
    <div class="mv-grid">
      <div class="mv-card"><div class="ico">🎯</div><h3><?= e(t('mission_label')) ?></h3><p><?= e(get_setting('mission')) ?></p></div>
      <div class="mv-card"><div class="ico">👁️</div><h3><?= e(t('vision_label')) ?></h3><p><?= e(get_setting('vision')) ?></p></div>
      <div class="mv-card"><div class="ico">💝</div><h3><?= e(t('values_label')) ?></h3><p><?= e(get_setting('values_text')) ?></p></div>
    </div>
  </div>
</section>

<section class="timeline">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ OUR JOURNEY</span>
      <h2>Milestones of <span>Grace</span></h2>
      <p>A look back at God's faithfulness across the years.</p>
    </div>
    <div style="position:relative;max-width:900px;margin:0 auto">
      <div class="timeline-line"></div>
      <?php if ($milestones): foreach ($milestones as $ms): ?>
        <div class="tl-item">
          <div class="tl-card" style="<?= $ms['is_highlight'] ? 'border-top-width:5px;background:linear-gradient(135deg,#fff,#f0f7ff)' : '' ?>">
            <span class="tl-year"><?= e($ms['year']) ?></span>
            <h4><?= e($ms['icon']) ?> <?= e(current_lang()==='hi' && $ms['title_hi'] ? $ms['title_hi'] : $ms['title']) ?></h4>
            <p><?= e(current_lang()==='hi' && $ms['description_hi'] ? $ms['description_hi'] : $ms['description']) ?></p>
          </div>
        </div>
      <?php endforeach; else: ?>
        <!-- Fallback if milestones table not migrated -->
        <div class="tl-item"><div class="tl-card"><span class="tl-year">2012</span><h4>The Beginning</h4><p>Started in Sangvi, Pune with 20 children in a rented classroom.</p></div></div>
        <div class="tl-item"><div class="tl-card"><span class="tl-year">2024</span><h4>1,000+ Lives</h4><p>Impacting 1,000+ lives through education and care.</p></div></div>
      <?php endif; ?>

    </div>
  </div>
</section>

<section class="team">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ OUR LEADERSHIP</span>
      <h2>Meet Our <span>Team</span></h2>
      <p>Dedicated servants working tirelessly to bring hope to the hopeless.</p>
    </div>
    <?php if (!$team): ?>
      <p style="text-align:center;color:var(--gray)">Team members will be listed here soon.</p>
    <?php else: ?>
    <div class="team-grid">
      <?php foreach ($team as $mem):
        $has_img = !empty($mem['image']) && file_exists(__DIR__ . '/../' . $mem['image']); ?>
        <div class="team-card">
          <div class="team-avatar" style="<?= $has_img ? "background-image:url('".BASE_URL.e($mem['image'])."')" : '' ?>">
            <?php if (!$has_img): ?><?= strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$mem['name']),0,2)) ?><?php endif; ?>
          </div>
          <div class="team-body">
            <h4><?= e(tr_field($mem, 'name')) ?></h4>
            <?php if ($mem['role']): ?><div class="role"><?= e(tr_field($mem, 'role')) ?></div><?php endif; ?>
            <?php if ($mem['bio']): ?><p><?= e(tr_field($mem, 'bio')) ?></p><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<section style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;text-align:center;padding:4rem 1rem">
  <?php $motto = current_lang()==='hi' && get_setting('motto_hi') ? get_setting('motto_hi') : get_setting('motto'); ?>
  <?php if ($motto): ?>
    <div style="font-size:clamp(1.1rem,2vw,1.6rem);font-weight:600;letter-spacing:2px;color:var(--accent);margin-bottom:1rem;text-transform:uppercase">✦ <?= e($motto) ?> ✦</div>
  <?php endif; ?>
  <h2 style="font-size:clamp(1.6rem,3vw,2.4rem);margin-bottom:1rem">Join Us in Making a Difference</h2>
  <p style="max-width:600px;margin:0 auto 2rem;opacity:.95">Whether through prayer, partnership, or generosity — your role matters.</p>
  <a href="<?= BASE_URL ?>pages/donate.php" class="btn btn-primary">Donate Now ♥</a> &nbsp;
  <a href="<?= BASE_URL ?>pages/volunteer.php" class="btn btn-outline">Volunteer</a>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
