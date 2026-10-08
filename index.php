<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$programs = $pdo->query("SELECT * FROM programs WHERE status='active' ORDER BY display_order, id LIMIT 8")->fetchAll();
$testimonials = $pdo->query("SELECT * FROM testimonials WHERE status='active' ORDER BY display_order, id LIMIT 3")->fetchAll();

// Marquee announcements — ticker shown between the hero and stats.
// Empty/missing table => section hidden automatically.
$marquees = [];
try {
    $marquees = $pdo->query("SELECT * FROM marquees WHERE status='active' ORDER BY display_order, id")->fetchAll();
} catch (Throwable $e) { /* table not migrated yet — marquee stays hidden */ }

if ($marquees) {
    $mq_len = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
    $mq_chars = 0;
    foreach ($marquees as $m) {
        $mq_chars += $mq_len((string)($m['icon'] ?? '')) + 1 + $mq_len((string)($m['text'] ?? ''));
    }
    $marquee_dur = max(18, min(90, (int)round($mq_chars * 0.3)));
} else {
    $marquee_dur = 30;
}

// Hero slides for the carousel — graceful fallback if table doesn't exist yet
$slides = [];
try {
    $slides = $pdo->query("SELECT * FROM hero_slides WHERE status='active' ORDER BY display_order, id")->fetchAll();
} catch (Throwable $e) { /* table not migrated yet */ }

// Carousel settings (with sensible defaults)
$carousel_config = [
    'autoplay'     => (int)(get_setting('carousel_autoplay', 1) ?? 1),
    'interval'     => (int)(get_setting('carousel_interval', 6000) ?: 6000),
    'pause_hover'  => (int)(get_setting('carousel_pause_hover', 1) ?? 1),
    'show_arrows'  => (int)(get_setting('carousel_show_arrows', 1) ?? 1),
    'show_dots'    => (int)(get_setting('carousel_show_dots', 1) ?? 1),
    'show_counter' => (int)(get_setting('carousel_show_counter', 1) ?? 1),
    'transition'   => get_setting('carousel_transition', 'fade') ?: 'fade',
    'video_audio'  => (int)(get_setting('carousel_video_audio', 0) ?? 0),
    'show_text'    => (int)(get_setting('carousel_show_text', 1) ?? 1),
];

/** Extract a YouTube video ID from any URL form, or pass through if already an ID */
function youtube_id($s) {
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/))([\w-]{6,})~', $s, $m)) return $m[1];
    return preg_match('~^[\w-]{6,15}$~', $s) ? $s : '';
}
/** Extract a Vimeo video ID from any URL form */
function vimeo_id($s) {
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $s, $m)) return $m[1];
    return preg_match('~^\d+$~', $s) ? $s : '';
}

// Aggregate stats
$total_programs   = $pdo->query("SELECT COUNT(*) FROM programs WHERE status='active'")->fetchColumn();
$total_volunteers = $pdo->query("SELECT COUNT(*) FROM volunteers WHERE status='approved'")->fetchColumn();

$page_title = 'Home';
$page_desc = 'Sharan Foundation — a Christian charity serving India & UK through education, shelter and faith.';
$current_page = 'home';
$extra_head = '<link rel="stylesheet" href="' . BASE_URL . 'css/home.css">';

require __DIR__ . '/includes/public_header.php';
?>

<!-- HERO CAROUSEL -->
<section class="hero hero-carousel trans-<?= e($carousel_config['transition']) ?>" id="home"
         data-autoplay="<?= $carousel_config['autoplay'] ?>"
         data-interval="<?= (int)$carousel_config['interval'] ?>"
         data-pause-hover="<?= $carousel_config['pause_hover'] ?>">

<?php if (!$slides): ?>
  <!-- Fallback single hero when no slides configured -->
  <div class="hero-slides">
    <div class="hero-slide active overlay-blue text-left<?= $carousel_config['show_text'] ? '' : ' text-hidden' ?>">
      <div class="bg" style="background-image:url('<?= e(site_image_url('home_hero', 'images/hero.jpg')) ?>')"></div>
      <?php if ($carousel_config['show_text']): ?>
      <div class="container">
        <div class="hero-content">
          <span class="tag"><?= e(t('hero_badge')) ?></span>
          <h1><?= e(t('hero_title_1')) ?> <span><?= e(t('hero_title_2')) ?></span> <?= e(t('hero_title_3')) ?></h1>
          <p><?= e(get_setting('about_short', 'Sharan Foundation is a charitable ministry dedicated to empowering children, girls, women, and the elderly through education, shelter, and the love of Christ.')) ?></p>
          <div class="hero-cta">
            <a href="<?= BASE_URL ?>pages/donate.php" class="btn btn-primary"><?= e(t('btn_donate_now')) ?> ♥</a>
            <a href="<?= BASE_URL ?>pages/about.php" class="btn btn-outline"><?= e(t('btn_learn_more')) ?> →</a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>

  <div class="hero-slides" id="heroSlides">
    <?php foreach ($slides as $i => $sl):
      $media_type = $sl['media_type'] ?? 'image';
      $has_img = !empty($sl['image']) && file_exists(__DIR__ . '/' . $sl['image']);
      $poster_url = $has_img ? BASE_URL . e($sl['image']) : site_image_url('home_hero', 'images/hero.jpg');
      $overlay = $sl['overlay_color'] ?: 'blue';
      $pos = $sl['text_position'] ?: 'left';
      $title    = tr_field($sl, 'title');
      $subtitle = tr_field($sl, 'subtitle');
      $desc     = tr_field($sl, 'description');
      $cta1     = tr_field($sl, 'cta_text')   ?: 'Learn More';
      $cta2     = tr_field($sl, 'cta_text_2');
      $audio_attr = $carousel_config['video_audio'] ? '' : 'muted';
      // Per-slide + global control: hide the text overlay entirely when turned off
      $text_visible = $carousel_config['show_text'] && (int)($sl['show_text'] ?? 1) === 1;
    ?>
      <div class="hero-slide overlay-<?= e($overlay) ?> text-<?= e($pos) ?> media-<?= e($media_type) ?> <?= $i===0?'active':'' ?><?= $text_visible ? '' : ' text-hidden' ?>"
           data-index="<?= $i ?>"
           data-media="<?= e($media_type) ?>"
           data-loaded="<?= $media_type==='image' ? '1' : '0' ?>">

        <?php if ($media_type === 'image'): ?>
          <div class="bg" style="background-image:url('<?= $poster_url ?>')"></div>

        <?php elseif ($media_type === 'video' && !empty($sl['video_file']) && file_exists(__DIR__ . '/' . $sl['video_file'])): ?>
          <video class="video-bg" <?= $audio_attr ?> loop playsinline preload="metadata" poster="<?= $poster_url ?>" data-src="<?= BASE_URL . e($sl['video_file']) ?>">
            <!-- src injected by JS only when slide becomes active (saves bandwidth) -->
          </video>
          <div class="video-overlay"></div>

        <?php elseif ($media_type === 'youtube' && ($yt_id = youtube_id($sl['video_url'] ?? ''))): ?>
          <div class="video-loading"></div>
          <!-- iframe injected only when slide becomes active -->
          <div class="iframe-wrap" data-iframe-src="https://www.youtube.com/embed/<?= e($yt_id) ?>?autoplay=1&mute=<?= $carousel_config['video_audio']?'0':'1' ?>&loop=1&playlist=<?= e($yt_id) ?>&controls=0&showinfo=0&modestbranding=1&playsinline=1&rel=0"></div>
          <div class="video-overlay"></div>

        <?php elseif ($media_type === 'vimeo' && ($vm_id = vimeo_id($sl['video_url'] ?? ''))): ?>
          <div class="video-loading"></div>
          <div class="iframe-wrap" data-iframe-src="https://player.vimeo.com/video/<?= e($vm_id) ?>?autoplay=1&muted=<?= $carousel_config['video_audio']?'0':'1' ?>&loop=1&background=1&autopause=0"></div>
          <div class="video-overlay"></div>

        <?php else: /* fallback to poster image */ ?>
          <div class="bg" style="background-image:url('<?= $poster_url ?>')"></div>
        <?php endif; ?>

        <?php if ($text_visible): ?>
        <div class="container">
          <div class="hero-content">
            <?php if ($sl['badge_text']): ?>
              <span class="tag"><?= e($sl['badge_text']) ?></span>
            <?php endif; ?>
            <?php if ($subtitle): ?>
              <div class="subtitle"><?= e($subtitle) ?></div>
            <?php endif; ?>
            <h1><?= e($title) ?></h1>
            <?php if ($desc): ?>
              <p><?= nl2br(e($desc)) ?></p>
            <?php endif; ?>
            <div class="hero-cta">
              <a href="<?= BASE_URL . e(ltrim($sl['cta_link'] ?: '#', '/')) ?>" class="btn btn-primary"><?= e($cta1) ?> →</a>
              <?php if ($cta2 && $sl['cta_link_2']): ?>
                <a href="<?= BASE_URL . e(ltrim($sl['cta_link_2'], '/')) ?>" class="btn btn-outline"><?= e($cta2) ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div><?php endif; ?>

      </div>
    <?php endforeach; ?>
  </div>

  <?php if (count($slides) > 1): ?>
    <?php if ($carousel_config['show_counter']): ?>
    <div class="hero-counter"><span id="heroCurrent">1</span> / <?= count($slides) ?></div>
    <?php endif; ?>

    <?php if ($carousel_config['show_arrows']): ?>
    <button class="hero-nav prev" id="heroPrev" aria-label="Previous slide">‹</button>
    <button class="hero-nav next" id="heroNext" aria-label="Next slide">›</button>
    <?php endif; ?>

    <?php if ($carousel_config['show_dots']): ?>
    <div class="hero-dots" id="heroDots">
      <?php foreach ($slides as $i => $_): ?>
        <button class="hero-dot <?= $i===0?'active':'' ?>" data-go="<?= $i ?>" aria-label="Go to slide <?= $i+1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

<?php endif; ?>
</section>

<script>
(function(){
  const carousel = document.querySelector('.hero-carousel');
  const slidesEl = document.getElementById('heroSlides');
  if (!carousel || !slidesEl) return;
  const slides   = slidesEl.querySelectorAll('.hero-slide');
  if (slides.length < 1) return;

  const dots     = document.querySelectorAll('#heroDots .hero-dot');
  const current  = document.getElementById('heroCurrent');
  const autoplay = carousel.dataset.autoplay === '1';
  const interval = parseInt(carousel.dataset.interval, 10) || 6000;
  const pauseHover = carousel.dataset.pauseHover === '1';

  let index = 0;
  let timer = null;
  let isPaused = false;

  // ============ MEDIA HANDLING ============
  // Lazy-load the active slide's video, play it; pause + reset others.
  function loadSlideMedia(slide) {
    const type = slide.dataset.media;
    if (slide.dataset.loaded === '1' || type === 'image') return;

    // Local video file → inject src once
    const video = slide.querySelector('video.video-bg');
    if (video && video.dataset.src && !video.src) {
      video.src = video.dataset.src;
      video.load();
    }

    // YouTube/Vimeo iframe → inject once
    const wrap = slide.querySelector('.iframe-wrap[data-iframe-src]');
    if (wrap && !wrap.querySelector('iframe')) {
      const iframe = document.createElement('iframe');
      iframe.className = 'iframe-bg';
      iframe.src = wrap.dataset.iframeSrc;
      iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
      iframe.setAttribute('allowfullscreen', '');
      wrap.appendChild(iframe);
      // remove loading shimmer once iframe loads
      iframe.addEventListener('load', () => {
        const loader = slide.querySelector('.video-loading');
        if (loader) loader.style.display = 'none';
      });
    }

    slide.dataset.loaded = '1';
  }

  function playSlideVideo(slide) {
    const video = slide.querySelector('video.video-bg');
    if (video) {
      const p = video.play();
      if (p && typeof p.catch === 'function') p.catch(() => {/* autoplay blocked: silent */});
    }
  }
  function pauseSlideVideo(slide) {
    const video = slide.querySelector('video.video-bg');
    if (video && !video.paused) { try { video.pause(); video.currentTime = 0; } catch(e){} }
  }

  // For HTML5 video slides we want the carousel timer to wait the video's duration.
  // Use the slide's media type to compute "stayTime" dynamically.
  function stayTimeFor(slide) {
    const video = slide.querySelector('video.video-bg');
    if (video && video.duration && isFinite(video.duration) && video.duration > 3) {
      // Use min(video duration, configured interval * 2.5) — cap so very long videos still rotate
      return Math.min(video.duration * 1000, interval * 2.5);
    }
    // YouTube/Vimeo: use 1.5x interval (typical short loop)
    if (slide.dataset.media === 'youtube' || slide.dataset.media === 'vimeo') {
      return Math.max(interval, 10000);
    }
    return interval;
  }

  // ============ NAVIGATION ============
  function go(i) {
    const newIndex = (i + slides.length) % slides.length;
    if (newIndex === index && slides[index].classList.contains('active')) return;

    // For slide transition mode, track exiting slide
    if (carousel.classList.contains('trans-slide')) {
      slides[index].classList.add('exiting');
      setTimeout(() => slides[index]?.classList.remove('exiting'), 1100);
    }

    // Pause OLD slide's video
    pauseSlideVideo(slides[index]);

    index = newIndex;
    slides.forEach((s,n) => s.classList.toggle('active', n === index));
    dots.forEach((d,n) => d.classList.toggle('active', n === index));
    if (current) current.textContent = index + 1;

    // Lazy-load + play NEW slide's media
    loadSlideMedia(slides[index]);
    playSlideVideo(slides[index]);

    // Reset timer based on this slide's stay time
    resetTimer();
  }
  function next() { go(index + 1); }
  function prev() { go(index - 1); }

  function resetTimer() {
    if (timer) clearTimeout(timer);
    if (!autoplay || isPaused) return;
    timer = setTimeout(next, stayTimeFor(slides[index]));
  }

  // ============ EVENT WIRING ============
  document.getElementById('heroNext')?.addEventListener('click', next);
  document.getElementById('heroPrev')?.addEventListener('click', prev);
  dots.forEach(d => d.addEventListener('click', () => go(+d.dataset.go)));

  // Touch swipe
  let touchStartX = 0;
  slidesEl.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, {passive:true});
  slidesEl.addEventListener('touchend',   e => {
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 50) (dx > 0 ? prev() : next());
  });

  // Pause on hover (desktop only)
  if (pauseHover && window.matchMedia('(hover:hover)').matches) {
    slidesEl.addEventListener('mouseenter', () => { isPaused = true; if (timer) clearTimeout(timer); });
    slidesEl.addEventListener('mouseleave', () => { isPaused = false; resetTimer(); });
  }

  // Pause when tab is not visible (saves battery + bandwidth)
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      isPaused = true;
      if (timer) clearTimeout(timer);
      pauseSlideVideo(slides[index]);
    } else {
      isPaused = false;
      playSlideVideo(slides[index]);
      resetTimer();
    }
  });

  // Keyboard arrows
  document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'ArrowLeft')  prev();
    if (e.key === 'ArrowRight') next();
  });

  // Initial: load + play the first slide's media
  loadSlideMedia(slides[0]);
  playSlideVideo(slides[0]);
  resetTimer();
})();
</script>

<?php if ($marquees): ?>
<!-- MARQUEE TICKER -->
<div class="marquee" aria-label="Announcements">
  <div class="marquee-track" style="--mq-dur: <?= $marquee_dur ?>s">
    <?php for ($copy = 0; $copy < 2; $copy++): ?>
    <div class="marquee-content"<?= $copy === 1 ? ' aria-hidden="true"' : '' ?>>
      <?php foreach ($marquees as $m): ?>
        <span class="marquee-item">
          <?php if (!empty($m['icon'])): ?><span class="mq-ico"><?= e($m['icon']) ?></span><?php endif; ?>
          <span class="mq-text"><?= e(tr_field($m, 'text')) ?></span>
        </span>
        <span class="marquee-sep" aria-hidden="true"><svg viewBox="0 0 24 24" width="13" height="13"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></span>
      <?php endforeach; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- STATS -->
<section class="stats">
  <div class="container">
    <div class="stats-grid">
      <div class="stat"><h3>2,500+</h3><p><?= e(t('stats_children')) ?></p></div>
      <div class="stat"><h3>15+</h3><p><?= e(t('stats_years')) ?></p></div>
      <div class="stat"><h3><?= (int)$total_programs ?></h3><p><?= e(t('stats_programs')) ?></p></div>
      <div class="stat"><h3>2</h3><p><?= e(t('stats_countries')) ?></p></div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about" class="sec-cream">
  <div class="container">
    <div class="grid-2">
      <div class="about-img" style="<?= e(site_bg_attr('story_img', 'linear-gradient(rgba(37,99,235,.15),rgba(26,46,53,.25))', 'images/about.jpg')) ?>"></div>
      <div class="about-text">
        <span class="tag"><?= e(t('about_badge')) ?></span>
        <h2><?= e(t('about_title')) ?></h2>
        <p><?= nl2br(e(get_setting('about_short'))) ?></p>
        <div class="verse">"Pure and undefiled religion before God is this: to visit orphans and widows in their trouble, and to keep oneself unspotted from the world." — James 1:27</div>
        <a href="<?= BASE_URL ?>pages/about.php" class="btn btn-primary"><?= e(t('btn_learn_more')) ?></a>
      </div>
    </div>
  </div>
</section>

<!-- MV -->
<section class="mv">
  <div class="container">
    <div class="section-head">
      <span class="tag"><?= e(t('mv_badge')) ?></span>
      <h2><?= e(t('mv_title')) ?></h2>
      <p><?= e(t('mv_subtitle')) ?></p>
    </div>
    <div class="mv-grid">
      <div class="mv-card"><div class="ico">🎯</div><h3><?= e(t('mission_label')) ?></h3><p><?= e(get_setting('mission')) ?></p></div>
      <div class="mv-card"><div class="ico">👁️</div><h3><?= e(t('vision_label')) ?></h3><p><?= e(get_setting('vision')) ?></p></div>
      <div class="mv-card"><div class="ico">💝</div><h3><?= e(t('values_label')) ?></h3><p><?= e(get_setting('values_text')) ?></p></div>
    </div>
  </div>
</section>

<!-- PROGRAMS (from DB) -->
<section class="programs" id="programs">
  <div class="container">
    <div class="section-head">
      <span class="tag"><?= e(t('programs_badge')) ?></span>
      <h2><?= e(t('programs_title')) ?></h2>
      <p><?= count($programs) ?></p>
    </div>
    <div class="programs-grid">
      <?php
      $bg_colors = ['linear-gradient(135deg,#2563eb,#f4a261)','linear-gradient(135deg,#5b3a1f,#d4a017)','linear-gradient(135deg,#1a4d6e,#5fa8c9)','linear-gradient(135deg,#e76f51,#f4a261)'];
      foreach ($programs as $i => $p):
        $has_img = !empty($p['image']) && file_exists(__DIR__ . '/' . $p['image']);
      ?>
        <div class="program">
          <div class="program-img" style="<?= $has_img ? "background-image:url('".BASE_URL.e($p['image'])."')" : 'background:'.$bg_colors[$i % 4] ?>">
            <?php if (!$has_img): ?><span style="z-index:1;text-shadow:0 4px 14px rgba(0,0,0,.3)"><?= e($p['icon']) ?></span><?php endif; ?>
            <div class="icon-circle"><?= e($p['icon']) ?></div>
          </div>
          <div class="program-body">
            <h3><?= e(tr_field($p, 'title')) ?></h3>
            <p><?= e(tr_field($p, 'short_desc')) ?></p>
            <a href="<?= BASE_URL ?>pages/programs.php#<?= e($p['slug']) ?>"><?= e(t('btn_learn_more')) ?> →</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- LOCATIONS -->
<section class="locations" id="locations">
  <div class="container">
    <div class="section-head">
      <span class="tag" style="background:rgba(244,162,97,.2);color:var(--accent)"><?= e(t('locations_badge')) ?></span>
      <h2 style="color:#fff"><?= e(t('locations_title')) ?></h2>
      <p><?= e(t('locations_sub')) ?></p>
    </div>
    <div class="loc-grid">
      <div class="loc-card"><div class="flag">🇮🇳</div><h3><?= e(t('india_ops')) ?></h3><p><?= e(get_setting('address_in')) ?></p><ul><li>Child & girl education programs</li><li>Women empowerment & skills training</li><li>Old-age homes & care facilities</li><li>Boys' & girls' hostels</li><li>Acts Bible College</li></ul></div>
      <div class="loc-card"><div class="flag">🇬🇧</div><h3><?= e(t('uk_ops')) ?></h3><p><?= e(get_setting('address_uk')) ?></p><ul><li>Fundraising & sponsorships</li><li>Volunteer & partnership programs</li><li>Local community outreach</li><li>Mission awareness & advocacy</li><li>Prayer & support network</li></ul></div>
    </div>
  </div>
</section>

<!-- DONATE CTA -->
<section class="cta-band" style="<?= e(site_bg_attr('donate_bg', 'linear-gradient(rgba(37,99,235,.88),rgba(29,78,216,.88))', 'images/donate-bg.jpg')) ?>">
  <div class="container">
    <h2><?= e(t('donate_band_title')) ?></h2>
    <p><?= e(t('donate_band_text')) ?></p>
    <a href="<?= BASE_URL ?>pages/donate.php" class="btn btn-primary"><?= e(t('btn_donate_now')) ?> ♥</a> &nbsp;
    <a href="<?= BASE_URL ?>pages/partner.php" class="btn btn-outline"><?= e(t('nav_partner')) ?></a>
  </div>
</section>

<!-- TESTIMONIALS (from DB) -->
<?php if ($testimonials): ?>
<section>
  <div class="container">
    <div class="section-head">
      <span class="tag"><?= e(t('testimonials_badge')) ?></span>
      <h2><?= e(t('testimonials_title')) ?></h2>
    </div>
    <div class="test-grid">
      <?php foreach ($testimonials as $tm): ?>
        <div class="test-card">
          <div class="quote">"</div>
          <p><?= e(tr_field($tm, 'message')) ?></p>
          <div class="test-author">
            <div class="avatar"><?= strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$tm['name']),0,2)) ?></div>
            <div><h4><?= e(tr_field($tm, 'name')) ?></h4><span><?= e(tr_field($tm, 'role')) ?></span></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/public_footer.php'; ?>
