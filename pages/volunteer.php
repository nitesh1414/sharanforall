<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_volunteer');
$page_desc = 'Join Sharan Foundation as a volunteer - share your time, skills and passion to make a real difference.';
$current_page = 'volunteer';
$extra_head = '<style>
  .form-card{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:2.5rem;max-width:900px;margin:0 auto}
  .form-section{margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:1px solid #eee}
  .form-section:last-child{border-bottom:none}
  .form-section h3{color:var(--primary-dark);font-size:1.1rem;margin-bottom:1.2rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  .form-group{margin-bottom:1rem}
  .form-group label{display:block;font-weight:600;margin-bottom:.4rem;color:#444;font-size:.9rem}
  .form-group .req{color:#e74c3c}
  .form-group input,.form-group select,.form-group textarea{width:100%;padding:.8rem 1rem;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:.95rem;background:#fff;transition:.2s}
  .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
  .form-group textarea{resize:vertical;min-height:90px}
  .why-volunteer{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-bottom:3rem}
  .why-card{background:#fff;padding:1.8rem;border-radius:12px;box-shadow:var(--shadow);text-align:center;border-top:3px solid var(--accent)}
  .why-card .ico{font-size:2.2rem;margin-bottom:.6rem}
  .why-card h4{color:var(--primary-dark);margin-bottom:.4rem}
  .why-card p{color:var(--gray);font-size:.9rem}
  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500;animation:slideDown .4s ease}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}
  @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
  @media(max-width:780px){.form-card{padding:1.5rem}.form-row{grid-template-columns:1fr}}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_volunteer', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_volunteer')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_volunteer')) ?></div>
  </div>
</section>

<section class="sec-cream">
  <div class="container">
    <div class="section-head">
      <span class="tag"><?= e(t('why_volunteer')) ?></span>
      <h2><?= e(t('be_the_change')) ?></h2>
    </div>
    <div class="why-volunteer">
      <div class="why-card"><div class="ico">❤️</div><h4>Make Real Impact</h4><p>Directly serve children, women & elders in need.</p></div>
      <div class="why-card"><div class="ico">🌱</div><h4>Grow Personally</h4><p>Gain experience, skills, and meaningful relationships.</p></div>
      <div class="why-card"><div class="ico">🌍</div><h4>Global Community</h4><p>Be part of a network across India & the UK.</p></div>
      <div class="why-card"><div class="ico">🙏</div><h4>Walk in Faith</h4><p>Serve as the hands and feet of Jesus.</p></div>
    </div>
  </div>
</section>

<section class="sec-sky">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ <?= strtoupper(e(t('nav_volunteer'))) ?></span>
      <h2><?= e(t('volunteer_apply')) ?></h2>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>" style="max-width:900px;margin:0 auto 1.5rem"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>api/submit_volunteer.php" method="post" class="form-card">

      <div class="form-section">
        <h3><?= e(t('personal_info')) ?></h3>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('full_name')) ?> <span class="req">*</span></label><input type="text" name="full_name" required></div>
          <div class="form-group"><label><?= e(t('email')) ?> <span class="req">*</span></label><input type="email" name="email" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('phone_number')) ?> <span class="req">*</span></label><input type="tel" name="phone" required></div>
          <div class="form-group"><label><?= e(t('age')) ?></label><input type="number" name="age" min="14" max="99"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('gender')) ?></label><select name="gender">
            <option value="">—</option><option>Male</option><option>Female</option><option>Other</option>
          </select></div>
          <div class="form-group"><label><?= e(t('occupation')) ?></label><input type="text" name="occupation"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('country')) ?></label><input type="text" name="country"></div>
          <div class="form-group"><label><?= e(t('city')) ?></label><input type="text" name="city"></div>
        </div>
      </div>

      <div class="form-section">
        <h3><?= e(t('volunteering_prefs')) ?></h3>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('area_interest')) ?> <span class="req">*</span></label><select name="area_of_interest" required>
            <option value="">-- Select an Area --</option>
            <option>Child Education</option><option>Girl Child Education</option><option>Women Empowerment</option>
            <option>Old Age Home</option><option>Shelter Home</option><option>Girls' Hostel</option>
            <option>Boys' Hostel</option><option>Bible College</option><option>Fundraising</option>
            <option>Event Management</option><option>Administration</option><option>Healthcare</option>
            <option>Where Most Needed</option>
          </select></div>
          <div class="form-group"><label><?= e(t('availability')) ?></label><select name="availability">
            <option value="">—</option>
            <option>Weekends only</option><option>Weekdays only</option><option>Few hours/week</option>
            <option>Full time</option><option>Short-term project</option><option>Long-term commitment</option>
          </select></div>
        </div>
        <div class="form-group"><label><?= e(t('skills')) ?></label><textarea name="skills"></textarea></div>
        <div class="form-group"><label><?= e(t('experience')) ?></label><textarea name="experience"></textarea></div>
        <div class="form-group"><label><?= e(t('motivation')) ?></label><textarea name="motivation"></textarea></div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:.7rem"><?= e(t('submit_application')) ?> →</button>
      <p style="text-align:center;margin-top:1rem;color:var(--gray)"><?= e(t('response_time_vol')) ?></p>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
