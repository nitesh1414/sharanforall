<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/i18n.php';
$page_title = t('page_partner');
$page_desc = 'Partner with Sharan Foundation through CSR, churches, NGOs or individual support — and create lasting change.';
$current_page = 'partner';
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
  .partner-types{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-bottom:3rem}
  .ptype{background:#fff;padding:1.8rem;border-radius:12px;box-shadow:var(--shadow);text-align:center;border-top:3px solid var(--primary);transition:.3s}
  .ptype:hover{transform:translateY(-5px)}
  .ptype .ico{font-size:2.2rem;margin-bottom:.6rem}
  .ptype h4{color:var(--primary-dark);margin-bottom:.4rem}
  .ptype p{color:var(--gray);font-size:.9rem}
  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}
  @media(max-width:780px){.form-card{padding:1.5rem}.form-row{grid-template-columns:1fr}}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_partner', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1><?= e(t('page_partner')) ?></h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <?= e(t('nav_partner')) ?></div>
  </div>
</section>

<section class="sec-peach">
  <div class="container">
    <div class="section-head">
      <span class="tag"><?= e(t('partner_opps')) ?></span>
      <h2><?= e(t('work_together')) ?></h2>
    </div>
    <div class="partner-types">
      <div class="ptype"><div class="ico">🏢</div><h4>Corporate / CSR</h4><p>Strategic CSR partnerships for sustainable, measurable impact.</p></div>
      <div class="ptype"><div class="ico">⛪</div><h4>Church Partnership</h4><p>Mission partnerships, mission teams, prayer support & sponsorship.</p></div>
      <div class="ptype"><div class="ico">🤲</div><h4>NGO Collaboration</h4><p>Joint programs, shared resources & combined community outreach.</p></div>
      <div class="ptype"><div class="ico">💝</div><h4>Foundation Grants</h4><p>Long-term funding for capacity-building and program expansion.</p></div>
    </div>
  </div>
</section>

<section class="sec-sage">
  <div class="container">
    <div class="section-head">
      <span class="tag"><?= e(t('partner_inquiry')) ?></span>
      <h2><?= e(t('build_meaningful')) ?></h2>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>" style="max-width:900px;margin:0 auto 1.5rem"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>api/submit_partner.php" method="post" class="form-card">

      <div class="form-section">
        <h3><?= e(t('org_info')) ?></h3>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('org_name')) ?> <span class="req">*</span></label><input type="text" name="org_name" required></div>
          <div class="form-group"><label><?= e(t('org_type')) ?></label><select name="org_type">
            <option>Corporate</option><option>Church</option><option>NGO</option><option>Foundation</option><option>Individual</option><option>Other</option>
          </select></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('website')) ?></label><input type="url" name="website" placeholder="https://"></div>
          <div class="form-group"><label><?= e(t('country')) ?></label><input type="text" name="country"></div>
        </div>
      </div>

      <div class="form-section">
        <h3>👤 <?= e(t('contact_person')) ?></h3>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('full_name')) ?> <span class="req">*</span></label><input type="text" name="contact_person" required></div>
          <div class="form-group"><label><?= e(t('designation')) ?></label><input type="text" name="designation"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('email')) ?> <span class="req">*</span></label><input type="email" name="email" required></div>
          <div class="form-group"><label><?= e(t('phone')) ?> <span class="req">*</span></label><input type="tel" name="phone" required></div>
        </div>
      </div>

      <div class="form-section">
        <h3><?= e(t('partner_details')) ?></h3>
        <div class="form-row">
          <div class="form-group"><label><?= e(t('partner_type')) ?></label><select name="partnership_type">
            <option value="">-- Select --</option>
            <option>One-time Donation</option><option>Monthly / Annual Sponsorship</option>
            <option>Project-based Funding</option><option>CSR Partnership</option>
            <option>Skills-based / In-kind</option><option>Mission / Volunteer Team</option>
            <option>Awareness & Advocacy</option><option>Other</option>
          </select></div>
          <div class="form-group"><label><?= e(t('budget_range')) ?></label><select name="budget_range">
            <option value="">-- Select --</option>
            <option>Under £1,000 / ₹1 Lakh</option>
            <option>£1,000 - £5,000 / ₹1L - 5L</option>
            <option>£5,000 - £25,000 / ₹5L - 25L</option>
            <option>£25,000+ / ₹25L+</option>
            <option>Open / Discuss</option>
          </select></div>
        </div>
        <div class="form-group"><label><?= e(t('programs_interest')) ?></label><textarea name="programs_of_interest"></textarea></div>
        <div class="form-group"><label><?= e(t('proposal')) ?></label><textarea name="proposal" rows="5"></textarea></div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:1rem;font-size:1.05rem"><?= e(t('submit_inquiry')) ?> →</button>
      <p style="text-align:center;margin-top:1rem;color:var(--gray);font-size:.85rem"><?= e(t('response_time_partner')) ?></p>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
