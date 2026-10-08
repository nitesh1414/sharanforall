<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_contact');
$page_desc = 'Get in touch with Sharan Foundation in India or UK. Donate, volunteer, or partner with us.';
$current_page = 'contact';
$extra_head = '<style>
  .contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:3rem}
  .contact-info h3{color:var(--primary-dark);margin-bottom:1.2rem;font-size:16px;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block}
  .contact-item{display:flex;gap:1rem;margin-bottom:1.4rem;align-items:flex-start}
  .contact-item .ico{width:45px;height:45px;border-radius:10px;background:rgba(37,99,235,.1);color:var(--primary);display:grid;place-items:center;font-size:1.2rem;flex-shrink:0}
  .contact-item h4{color:var(--dark);margin-bottom:.2rem;font-size:14px}
  .contact-item p{color:var(--ink-muted);font-size:14px}
  form.contact-form{background:#fff;padding:2.2rem;border-radius:14px;box-shadow:var(--shadow)}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  form.contact-form input,form.contact-form textarea,form.contact-form select{width:100%;padding:.85rem 1rem;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:.95rem;background:#fff;margin-bottom:1rem;transition:.25s}
  form.contact-form input:focus,form.contact-form textarea:focus,form.contact-form select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
  form.contact-form textarea{resize:vertical;min-height:120px}
  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}
  .donate-section{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:#fff;padding:5rem 0}
  .donate-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;margin-top:2.5rem}
  .donate-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);padding:2rem;border-radius:14px;text-align:center;transition:.3s;cursor:pointer}
  .donate-card:hover,.donate-card.featured{background:rgba(255,255,255,.15);transform:translateY(-5px);border-color:var(--accent)}
  .donate-card.featured{border:2px solid var(--accent)}
  .donate-card .amt{font-size:2.5rem;color:var(--accent);font-weight:800;margin:.5rem 0}
  .donate-card .desc{opacity:.9;font-size:.9rem;margin-bottom:1.2rem}
  .donate-card .btn{width:100%}
  .donate-badge{background:var(--accent);color:#fff;padding:.2rem .8rem;border-radius:50px;font-size:.7rem;font-weight:600;display:inline-block;margin-bottom:.5rem;letter-spacing:1px}
  .payment-methods{margin-top:3rem;text-align:center}
  .payment-methods h3{color:#fff;margin-bottom:1rem;font-size:1.2rem}
  .methods{display:flex;justify-content:center;gap:1rem;flex-wrap:wrap}
  .method{background:rgba(255,255,255,.1);padding:.7rem 1.4rem;border-radius:50px;font-size:.9rem;border:1px solid rgba(255,255,255,.2)}
  .map-placeholder{height:300px;background:linear-gradient(135deg,#1d4ed8,#0d2940);border-radius:14px;display:grid;place-items:center;color:#fff;text-align:center;padding:2rem;margin-top:2rem}
  .involved-card{cursor:pointer;text-decoration:none;color:inherit;display:block;transition:.3s}
  .involved-card:hover{transform:translateY(-5px)}
  @media(max-width:880px){.contact-grid{grid-template-columns:1fr}.form-row{grid-template-columns:1fr}}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_contact', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_contact')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_contact')) ?></div>
  </div>
</section>

<section id="contact-form" class="sec-cream">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ <?= strtoupper(e(t('we_love_hear'))) ?></span>
      <h2><?= e(t('lets_connect')) ?></h2>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>" style="max-width:900px;margin:0 auto 1.5rem"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="contact-grid">
      <div class="contact-info">
        <h3><?= e(t('india_office')) ?></h3>
        <div class="contact-item"><div class="ico">📍</div><div><h4><?= e(t('address')) ?></h4><p><?= nl2br(e(get_setting('address_in'))) ?></p></div></div>
        <div class="contact-item"><div class="ico">📞</div><div><h4><?= e(t('phone')) ?></h4><p><?= e(get_setting('phone_in')) ?></p></div></div>
        <div class="contact-item"><div class="ico">📧</div><div><h4><?= e(t('email')) ?></h4><p><?= e(get_setting('email_in')) ?></p></div></div>
        <div class="contact-item"><div class="ico">⏰</div><div><h4><?= e(t('office_hours')) ?></h4><p><?= e(t('india_hours')) ?></p></div></div>

        <h3 style="margin-top:2.5rem"><?= e(t('uk_office')) ?></h3>
        <div class="contact-item"><div class="ico">📍</div><div><h4><?= e(t('address')) ?></h4><p><?= nl2br(e(get_setting('address_uk'))) ?></p></div></div>
        <div class="contact-item"><div class="ico">📞</div><div><h4><?= e(t('phone')) ?></h4><p><?= e(get_setting('phone_uk')) ?></p></div></div>
        <div class="contact-item"><div class="ico">📧</div><div><h4><?= e(t('email')) ?></h4><p><?= e(get_setting('email_uk')) ?></p></div></div>
        <div class="contact-item"><div class="ico">⏰</div><div><h4><?= e(t('office_hours')) ?></h4><p><?= e(t('uk_hours')) ?></p></div></div>
      </div>

      <form action="<?= BASE_URL ?>api/submit_contact.php" method="post" class="contact-form">
        <h3 style="color:var(--primary-dark);margin-bottom:1.2rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block"><?= e(t('send_message')) ?></h3>
        <div class="form-row">
          <input type="text" name="name" placeholder="<?= e(t('your_name')) ?> *" required>
          <input type="email" name="email" placeholder="<?= e(t('email_address')) ?> *" required>
        </div>
        <div class="form-row">
          <input type="tel" name="phone" placeholder="<?= e(t('phone_number')) ?>">
          <select name="interest" required>
            <option value=""><?= e(t('interested_in')) ?> *</option>
            <option>Making a Donation</option>
            <option>Volunteering</option>
            <option>Partnership</option>
            <option>Child Sponsorship</option>
            <option>Bible College Admission</option>
            <option>Visiting our Campus</option>
            <option>General Inquiry</option>
          </select>
        </div>
        <select name="office" required>
          <option value=""><?= e(t('preferred_office')) ?> *</option>
          <option>India Office</option>
          <option>UK Office</option>
          <option>Either</option>
        </select>
        <textarea name="message" placeholder="<?= e(t('your_message')) ?> *" required></textarea>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:1rem"><?= e(t('btn_send_message')) ?> →</button>
      </form>
    </div>

    <div class="map-placeholder">
      <div>
        <span style="font-size:3rem;display:block;margin-bottom:.5rem">🗺️</span>
        <h3>Find Us on the Map</h3>
        <p style="opacity:.9;margin-top:.5rem">Google Maps for India & UK offices can be embedded here</p>
      </div>
    </div>
  </div>
</section>

<!-- DONATE CTA -->
<section class="donate-section" id="donate" style="text-align:center">
  <div class="container">
    <span class="tag" style="background:rgba(244,162,97,.2);color:var(--accent)">✦ <?= strtoupper(e(t('nav_donate'))) ?></span>
    <h2 style="color:#fff;margin-top:.6rem"><?= e(t('donate_title')) ?></h2>
    <p style="color:#bfc8cb;max-width:600px;margin:0 auto 2rem"><?= e(t('donate_subtitle')) ?></p>
    <a href="<?= BASE_URL ?>pages/donate.php" class="btn btn-primary" style="padding:.6rem 1.4rem">💝 <?= e(t('btn_donate_now')) ?> →</a>
    <div class="payment-methods">
      <h3><?= e(t('we_accept')) ?></h3>
      <div class="methods">
        <span class="method">🇮🇳 UPI / Razorpay</span>
        <span class="method">🇬🇧 Stripe</span>
        <span class="method">PayPal</span>
        <span class="method">Bank Transfer</span>
        <span class="method">Cheque / DD</span>
      </div>
      <p style="opacity:.85;font-size:.85rem;margin-top:1.5rem">
        🇮🇳 80G eligible in India &nbsp;|&nbsp; 🇬🇧 Gift Aid eligible in UK
      </p>
    </div>
  </div>
</section>

<section class="sec-sage">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ OTHER WAYS TO HELP</span>
      <h2>More Ways to <span>Get Involved</span></h2>
    </div>
    <div class="grid-3">
      <a href="<?= BASE_URL ?>pages/volunteer.php" class="card involved-card" style="padding:2rem;text-align:center">
        <div style="font-size:3rem;margin-bottom:1rem">🤝</div>
        <h3 style="color:var(--primary-dark);margin-bottom:.5rem">Volunteer</h3>
        <p style="color:var(--gray)">Lend your time and skills — teach, mentor, organize events or help.</p>
        <span style="color:var(--primary);font-weight:600;margin-top:.5rem;display:inline-block">Apply Now →</span>
      </a>
      <a href="<?= BASE_URL ?>pages/partner.php" class="card involved-card" style="padding:2rem;text-align:center">
        <div style="font-size:3rem;margin-bottom:1rem">🏢</div>
        <h3 style="color:var(--primary-dark);margin-bottom:.5rem">Partner With Us</h3>
        <p style="color:var(--gray)">CSR partnerships, church missions, NGO collaborations and grants.</p>
        <span style="color:var(--primary);font-weight:600;margin-top:.5rem;display:inline-block">Become a Partner →</span>
      </a>
      <a href="<?= BASE_URL ?>pages/contact.php#contact-form" class="card involved-card" style="padding:2rem;text-align:center">
        <div style="font-size:3rem;margin-bottom:1rem">🙏</div>
        <h3 style="color:var(--primary-dark);margin-bottom:.5rem">Prayer Partner</h3>
        <p style="color:var(--gray)">Join our prayer network and receive monthly updates.</p>
        <span style="color:var(--primary);font-weight:600;margin-top:.5rem;display:inline-block">Join Network →</span>
      </a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
