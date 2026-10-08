<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';
$flash = flash_get();
$current_page = $current_page ?? '';
$page_title = $page_title ?? 'Sharan Foundation';
$page_desc = $page_desc ?? 'A Christian charity serving India & UK through education, shelter and faith.';
$BU = BASE_URL;
$LANG = current_lang();
$gt_codes = json_encode(site_language_codes());
?><!DOCTYPE html>
<html lang="<?= e($LANG) ?>">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= e($page_title) ?> | Sharan Foundation</title>
<meta name="description" content="<?= e($page_desc) ?>" />

<!-- ============ PWA / Progressive Web App ============ -->
<link rel="manifest" href="<?= $BU ?>manifest.webmanifest" />
<meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)" />
<meta name="theme-color" content="#0d2940" media="(prefers-color-scheme: dark)" />
<meta name="color-scheme" content="light" />

<!-- Favicons (multi-format for every browser) -->
<link rel="icon" type="image/png" sizes="32x32" href="<?= $BU ?>icons/favicon-32.png" />
<link rel="icon" type="image/png" sizes="16x16" href="<?= $BU ?>icons/favicon-16.png" />
<link rel="shortcut icon" href="<?= $BU ?>favicon.ico" />

<!-- iOS / Safari (Add to Home Screen) -->
<link rel="apple-touch-icon" sizes="180x180" href="<?= $BU ?>icons/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="Sharan" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="format-detection" content="telephone=no" />

<!-- Windows tiles / MS Edge -->
<meta name="msapplication-TileColor" content="#2563eb" />
<meta name="msapplication-TileImage" content="<?= $BU ?>icons/icon-144.png" />
<meta name="msapplication-config" content="none" />
<meta name="application-name" content="Sharan Foundation" />

<!-- Open Graph (social sharing) -->
<meta property="og:type"        content="website" />
<meta property="og:title"       content="<?= e($page_title) ?> | Sharan Foundation" />
<meta property="og:description" content="<?= e($page_desc) ?>" />
<meta property="og:image"       content="<?= $BU ?>icons/icon-512.png" />
<meta property="og:site_name"   content="Sharan Foundation" />
<meta name="twitter:card"       content="summary_large_image" />
<meta name="twitter:title"      content="<?= e($page_title) ?>" />
<meta name="twitter:description" content="<?= e($page_desc) ?>" />
<meta name="twitter:image"      content="<?= $BU ?>icons/icon-512.png" />
<!-- ============ END PWA ============ -->

<!-- ============ FONTS (Poppins + Roboto) ============ -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" />

<link rel="stylesheet" href="<?= $BU ?>css/style.css" />
<style>
  /* Google Translate — same Google “G” icon, same place on every device.
     Fixed mid-right (no mobile move). Real widget sits on top (opacity 0)
     so a tap still opens the language list. */
  .gt-float{
    position:fixed;
    right:14px;
    top:50%;
    margin-top:-26px;          /* no transform — keeps GT dropdown anchored */
    z-index:10050;
    width:52px;height:52px;
    display:flex !important;
    align-items:center;justify-content:center;
    background:#fff;
    border:1px solid rgba(13,41,64,.18);
    border-radius:50%;
    box-shadow:0 6px 18px rgba(13,41,64,.22);
    cursor:pointer;
    overflow:visible;
    visibility:visible !important;
    opacity:1 !important;
    pointer-events:auto !important;
  }
  .gt-fallback{
    position:absolute;inset:0;
    display:grid;place-items:center;
    pointer-events:none;
    z-index:0;
  }
  .gt-g-icon{display:block;width:26px;height:26px}
  .gt-float #google_translate_element,
  .gt-float .goog-te-gadget,
  .gt-float .goog-te-gadget-simple{
    position:absolute;inset:0;
    width:52px !important;height:52px !important;
    max-width:52px;max-height:52px;
    opacity:0;                 /* invisible but clickable */
    cursor:pointer;
    z-index:2;
  }
  .gt-float .goog-te-gadget-simple{background:transparent;border:0;padding:0;box-shadow:none}
  .gt-float .goog-te-gadget span{display:none !important}
  .goog-te-banner-frame,.skiptranslate iframe.skiptranslate{display:none !important}
  .goog-logo-link{display:none !important}
  #goog-gt-tt{display:none !important}
  .goog-te-spinner-pos{display:none !important}
  iframe.goog-te-menu-frame{z-index:10060 !important}
  body{top:0 !important}
</style>
<?php if (!empty($extra_head)) echo $extra_head; ?>
<style id="site-type-scale">
  /* Applied last so Heading 16 / Sub 14 / Text+Button 14 wins over page extra_head.
     Poppins = titles, Roboto = body. 14px is the minimum readable size. */
  body,p,li,td,th,label,input,textarea,select,.help,.breadcrumb,.tag,.section-head p,
  .foot p,.foot li,.copy,nav a,.topbar,.topbar a,.checkbox-row span,.form-group .help{
    font-size:14px !important;
  }
  body,p,li,td,th,label,input,textarea,select{font-family:'Roboto','Poppins',sans-serif}
  h1,h2,.section-head h2,.page-header h1,.hero h1,.donate-hero h1,.fr-hero h1,.post-hero h1,
  .cta-band h2,.impact h2,.prog-content h2,.story h2,.about-text h2{
    font-size:16px !important;
    font-family:'Poppins','Roboto',sans-serif !important;
    font-weight:700;
    line-height:1.35;
  }
  h3,h4,h5,h6,.form-section h3,.contact-info h3,.why-card h4,.ptype h4,.program h3,.mv-card h3,
  .loc-card h3,.proj-body h3,.post h3,.team-body h4,.test-author h4,.bank-details h3,.bank-grid h4,
  .hero .subtitle,.prog-content .sub,.foot h4,.payment-methods h3{
    font-size:14px !important;
    font-family:'Poppins','Roboto',sans-serif !important;
    font-weight:600;
    line-height:1.4;
  }
  .btn,button.btn,.submit-btn,nav a.btn,input[type=submit],button[type=submit]{
    font-size:14px !important;
    font-family:'Poppins','Roboto',sans-serif !important;
  }
  /* Header lockup — name & tagline sized to the 100×100 mark */
  header.nav .logo-name{
    font-size:var(--logo-name-size,28px) !important;
    font-family:'Poppins','Roboto',sans-serif !important;
    font-weight:700;
    line-height:1.1;
  }
  header.nav .logo-text small{
    font-size:var(--logo-sub-size,16px) !important;
    font-family:'Roboto','Poppins',sans-serif !important;
    font-weight:500;
    line-height:1.2;
  }
  footer .logo-name{font-size:16px !important;font-family:'Poppins','Roboto',sans-serif !important}
  footer .logo-text small{font-size:14px !important;font-family:'Roboto','Poppins',sans-serif !important}
  /* Numeric displays stay at heading size so figures remain visible */
  .stat h3,.stats-bar h3,.tier .amount,.donate-card .amt,.prog-stats h4,.impact-stat h3{font-size:16px !important;font-family:'Poppins','Roboto',sans-serif !important}
</style>
</head>
<body>

<!-- Google Translate icon — fixed to the right side of the page, visible on web & mobile -->
<div class="gt-float" id="gt_float" title="Translate this website / अनुवाद करें" aria-label="Google Translate – select language">
  <span class="gt-fallback" aria-hidden="true">
    <svg class="gt-g-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false">
      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
    </svg>
  </span>
  <div id="google_translate_element"></div>
</div>

<!-- TOP BAR -->
<div class="topbar">
  <div class="container">
    <div>📧 <?= e(get_setting('email_in','contact@sharanforall.org')) ?> &nbsp;|&nbsp; 📞 <?= e(get_setting('phone_in','+91 98765 43210')) ?> (IN) &nbsp;|&nbsp; <?= e(get_setting('phone_uk','+44 20 1234 5678')) ?> (UK)</div>
    <div style="display:flex;align-items:center;flex-wrap:wrap">
      <a href="<?= $BU ?>pages/volunteer.php"><?= e(t('nav_volunteer')) ?></a>
      <a href="<?= $BU ?>pages/partner.php"><?= e(t('nav_partner')) ?></a>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<header class="nav">
  <div class="nav-inner">
    <a href="<?= $BU ?>" class="logo" aria-label="Sharan Foundation – Home">
      <div class="logo-mark"><img src="<?= $BU ?>images/logo.png" alt="Sharan Foundation logo"></div>
      <div class="logo-text">
        <span class="logo-name">Sharan Foundation</span>
        <small><?= e(t('site_tagline')) ?></small>
      </div>
    </a>
    <nav>
      <button class="menu-toggle" aria-label="Toggle menu">☰</button>
      <ul id="navlist">
        <li><a href="<?= $BU ?>" class="<?= $current_page==='home'?'active':'' ?>"><?= e(t('nav_home')) ?></a></li>
        <li><a href="<?= $BU ?>pages/about.php" class="<?= $current_page==='about'?'active':'' ?>"><?= e(t('nav_about')) ?></a></li>
        <li><a href="<?= $BU ?>pages/programs.php" class="<?= $current_page==='programs'?'active':'' ?>"><?= e(t('nav_programs')) ?></a></li>
        <li><a href="<?= $BU ?>pages/projects.php" class="<?= $current_page==='projects'?'active':'' ?>"><?= e(t('nav_projects')) ?></a></li>
        <li><a href="<?= $BU ?>pages/fundraisers.php" class="<?= $current_page==='fundraisers'?'active':'' ?>"><?= e(t('nav_fundraisers')) ?></a></li>
        <li><a href="<?= $BU ?>pages/gallery.php" class="<?= $current_page==='gallery'?'active':'' ?>"><?= e(t('nav_gallery')) ?></a></li>
        <li><a href="<?= $BU ?>pages/blog.php" class="<?= $current_page==='blog'?'active':'' ?>"><?= e(t('nav_blog')) ?></a></li>
        <li><a href="<?= $BU ?>pages/contact.php" class="<?= $current_page==='contact'?'active':'' ?>"><?= e(t('nav_contact')) ?></a></li>
        <li><a href="<?= $BU ?>pages/donate.php" class="btn btn-primary"><?= e(t('nav_donate')) ?> ♥</a></li>
      </ul>
    </nav>
  </div>
</header>

<!-- ===== Google Translate (client-side translation) ===== -->
<script type="text/javascript">
function googleTranslateElementInit(){
  if (window.__gtInitDone) return;
  window.__gtInitDone = true;
  var codes = <?= $gt_codes ?>; /* e.g. ["en","hi","fi"] — from Admin -> Languages & Translation */
  var el = document.getElementById('google_translate_element');
  if (!el) return;
  new google.translate.TranslateElement({
    pageLanguage: 'en',
    includedLanguages: codes.join(','),
    autoDisplay: false,
    layout: google.translate.TranslateElement.InlineLayout.SIMPLE
  }, 'google_translate_element');
}
</script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" async defer></script>

