<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$programs = $pdo->query("SELECT title FROM programs WHERE status='active' ORDER BY display_order")->fetchAll();

$page_title = 'Start a Fundraiser';
$page_desc = 'Start your own fundraising campaign for Sharan Foundation. Run a marathon, celebrate a birthday, or just because — every campaign creates impact.';
$current_page = 'fundraisers';

$extra_head = '<style>
  .form-card{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:2.5rem;max-width:900px;margin:0 auto}
  .form-section{margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:1px solid #eee}
  .form-section:last-of-type{border-bottom:none}
  .form-section h3{color:var(--primary-dark);font-size:1.15rem;margin-bottom:1.2rem;padding-bottom:.5rem;border-bottom:2px solid var(--accent);display:inline-block}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  .form-group{margin-bottom:1rem}
  .form-group label{display:block;font-weight:600;margin-bottom:.4rem;color:#444;font-size:.9rem}
  .form-group .req{color:#e74c3c}
  .form-group input,.form-group select,.form-group textarea{width:100%;padding:.85rem 1rem;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:.95rem;background:#fff;transition:.2s}
  .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
  .form-group textarea{resize:vertical;min-height:120px}
  .form-group .help{font-size:.8rem;color:#888;margin-top:.3rem}

  .how-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-bottom:3rem}
  .how-card{background:#fff;padding:1.8rem;border-radius:12px;box-shadow:var(--shadow);text-align:center;border-top:3px solid var(--accent);position:relative}
  .how-step{position:absolute;top:-15px;left:50%;transform:translateX(-50%);width:30px;height:30px;border-radius:50%;background:var(--accent);color:#fff;display:grid;place-items:center;font-weight:800}
  .how-card .ico{font-size:2.2rem;margin:.6rem 0}
  .how-card h4{color:var(--primary-dark);margin-bottom:.4rem}
  .how-card p{color:var(--gray);font-size:.88rem}

  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}

  .submit-btn{width:100%;padding:1.1rem;font-size:1.05rem;font-weight:700;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:10px;cursor:pointer;transition:.25s;letter-spacing:.5px;box-shadow:0 8px 20px rgba(231,111,81,.3)}
  .submit-btn:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(231,111,81,.45)}

  @media(max-width:780px){.form-card{padding:1.5rem}.form-row{grid-template-columns:1fr}}
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<section class="page-header" style="<?= e(site_bg_attr('banner_start_fundraiser', 'linear-gradient(rgba(29,78,216,.15),rgba(26,46,53,.15))', 'images/hero.jpg')) ?>">
  <div class="container">
    <h1>🚀 Start a Fundraiser</h1>
    <div class="breadcrumb"><a href="<?= BASE_URL ?>"><?= e(t('home')) ?></a> &nbsp;›&nbsp; <a href="<?= BASE_URL ?>pages/fundraisers.php">Fundraisers</a> &nbsp;›&nbsp; Start New</div>
  </div>
</section>

<section class="sec-peach">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ HOW IT WORKS</span>
      <h2>Raise Hope in <span>3 Easy Steps</span></h2>
    </div>
    <div class="how-grid">
      <div class="how-card"><span class="how-step">1</span><div class="ico">📝</div><h4>Tell Your Story</h4><p>Pick a cause, set a goal, and share why it matters to you.</p></div>
      <div class="how-card"><span class="how-step">2</span><div class="ico">📢</div><h4>Spread the Word</h4><p>Share your campaign with friends, family, and social media.</p></div>
      <div class="how-card"><span class="how-step">3</span><div class="ico">💝</div><h4>Make Impact</h4><p>100% of funds raised go directly to Sharan Foundation programs.</p></div>
    </div>
  </div>
</section>

<section class="sec-sage">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ CAMPAIGN DETAILS</span>
      <h2>Tell Us About Your <span>Fundraiser</span></h2>
      <p>Fill in the details below. Our team will review and activate your campaign within 1-2 business days.</p>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>" style="max-width:900px;margin:0 auto 1.5rem"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>api/submit_fundraiser.php" method="post" enctype="multipart/form-data" class="form-card">

      <div class="form-section">
        <h3>📖 Your Campaign</h3>
        <div class="form-group">
          <label>Campaign Title <span class="req">*</span></label>
          <input type="text" name="title" required placeholder="e.g. My 30th Birthday Fundraiser for Girls' Education">
          <p class="help">Make it personal and inspiring.</p>
        </div>
        <div class="form-group">
          <label>Cause / Program to Support</label>
          <select name="cause">
            <option value="Where Most Needed">Where Most Needed</option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= e($p['title']) ?>"><?= e($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Your Story <span class="req">*</span></label>
          <textarea name="story" rows="7" required placeholder="Tell us why you're fundraising. What inspired you? What will the funds achieve? Be personal — stories drive donations!"></textarea>
        </div>
        <div class="form-group">
          <label>Cover Image (optional)</label>
          <input type="file" name="cover_image" accept="image/*">
          <p class="help">A photo of you, the cause, or something meaningful. JPG/PNG, max 5MB.</p>
        </div>
      </div>

      <div class="form-section">
        <h3>🎯 Fundraising Goal</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Goal Amount <span class="req">*</span></label>
            <input type="number" name="goal_amount" min="100" step="1" required placeholder="50000">
          </div>
          <div class="form-group">
            <label>Currency</label>
            <select name="currency">
              <option value="INR">🇮🇳 INR (₹)</option>
              <option value="GBP">🇬🇧 GBP (£)</option>
              <option value="USD">🇺🇸 USD ($)</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime('+60 days')) ?>">
          </div>
        </div>
      </div>

      <div class="form-section">
        <h3>👤 About You (the Organizer)</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Full Name <span class="req">*</span></label>
            <input type="text" name="organizer_name" required>
          </div>
          <div class="form-group">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="organizer_email" required>
            <p class="help">We'll send approval confirmation and contribution alerts here.</p>
          </div>
        </div>
        <div class="form-group">
          <label>Phone Number <span class="req">*</span></label>
          <input type="tel" name="organizer_phone" required>
        </div>
        <div class="form-group">
          <label>A short bio about you</label>
          <textarea name="organizer_bio" rows="2" placeholder="e.g. Software engineer & mother of two from Mumbai"></textarea>
        </div>
      </div>

      <button type="submit" class="submit-btn">🚀 Submit My Fundraiser →</button>
      <p style="text-align:center;margin-top:1rem;color:var(--gray);font-size:.85rem">By submitting, you agree that funds raised will go to Sharan Foundation for the selected cause.</p>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
