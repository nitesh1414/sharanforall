<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/payments.php';

// Get programs to show as donation purposes
$programs = $pdo->query("SELECT title, title_hi, slug FROM programs WHERE status='active' ORDER BY display_order")->fetchAll();

// Live gateways (only those enabled AND with valid credentials)
$gateways = available_gateways();
$gateway_mode = gateway_mode();

// Donation stats (anonymized totals to build social proof)
$total_donors_completed = (int)$pdo->query("SELECT COUNT(DISTINCT email) FROM donations WHERE payment_status='completed'")->fetchColumn();
$total_raised_inr = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='INR'")->fetchColumn();
$total_raised_gbp = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE payment_status='completed' AND currency='GBP'")->fetchColumn();

$page_title = t('page_donate');
$page_desc  = 'Donate to Sharan Foundation - support child education, women empowerment, hostels, old age home and Bible college in India & UK.';
$current_page = 'donate';

$extra_head = '<style>
  .donate-hero{position:relative;color:#fff;text-align:center;padding:5rem 0;background:linear-gradient(rgba(29,78,216,.88),rgba(26,46,53,.88)),url(\''.BASE_URL.'images/donate-bg.jpg\') center/cover}
  .donate-hero h1{font-size:16px;font-weight:700;margin-bottom:.5rem;color:#fff}
  .donate-hero p{font-size:14px;opacity:.96;max-width:680px;margin:0 auto;color:#fff}

  .stats-bar{background:var(--bg-sky);padding:2rem 0;box-shadow:0 4px 12px rgba(0,0,0,.04)}
  .stats-bar-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1.5rem;text-align:center;align-items:stretch}
  .stats-bar-grid > div{display:flex;flex-direction:column;justify-content:center;align-items:center}
  .stats-bar h3{font-size:16px;color:var(--primary);font-weight:800;margin-bottom:.2rem}
  .stats-bar p{font-size:14px;color:var(--gray);text-transform:uppercase;letter-spacing:.6px}

  /* IMPACT TIERS — equal columns, equal height, smiley + amount + desc stacked */
  .section-toolbar{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.15rem}
  .section-toolbar h3{margin:0;padding:0;border:none;display:block}
  .impact-tiers{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:1rem;margin:0 0 1.5rem;align-items:stretch}
  .tier{background:#fff;border:2px solid #eee;border-radius:14px;padding:1.55rem .7rem 1.1rem;text-align:center;cursor:pointer;transition:.25s;position:relative;box-shadow:0 4px 14px rgba(0,0,0,.04);display:flex;flex-direction:column;align-items:center;justify-content:flex-start;height:100%;min-height:168px}
  .tier:hover{transform:translateY(-4px);border-color:var(--accent);box-shadow:0 12px 28px rgba(231,111,81,.18)}
  .tier.selected{border-color:var(--accent);background:#fffaf0;box-shadow:0 12px 28px rgba(231,111,81,.18)}
  .tier.popular{border-color:var(--accent)}
  .tier .ribbon{position:absolute;top:-11px;left:50%;transform:translateX(-50%);background:var(--accent);color:#fff;padding:.2rem .8rem;border-radius:50px;font-size:14px;font-weight:700;letter-spacing:1px;white-space:nowrap;line-height:1.2}
  .tier-smile{font-size:1.85rem;line-height:1;margin-bottom:.35rem}
  .tier .amount{font-size:16px;font-weight:800;color:var(--primary-dark);margin:.15rem 0 .35rem;line-height:1.2}
  .tier .currency{font-size:14px;color:var(--gray)}
  .tier .desc{font-size:14px;color:var(--gray);flex:1;display:flex;align-items:center;justify-content:center;min-height:2.6em;line-height:1.35;padding:0 .2rem}

  .currency-toggle{display:inline-flex;align-items:center;background:#f1f4f6;padding:.3rem;border-radius:50px;margin:0}
  .currency-toggle button{padding:.5rem 1.2rem;background:transparent;border:none;border-radius:50px;cursor:pointer;font-weight:600;font-size:14px;color:#666;transition:.2s;line-height:1.2}
  .currency-toggle button.active{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(37,99,235,.3)}

  /* FORM — every field shares the same label + control height so rows line up */
  .donate-form-card{background:#fff;border-radius:16px;box-shadow:var(--shadow);padding:2.5rem;max-width:1000px;margin:0 auto}
  .form-section{margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:1px solid #eee}
  .form-section:last-of-type{border-bottom:none}
  .form-section h3{color:var(--primary-dark);font-size:14px;margin:0 0 1.2rem;padding:0 0 .5rem;border-bottom:2px solid var(--accent);display:block}
  .form-section .section-toolbar h3{margin:0;padding:0;border:none}
  .form-row,.form-row-3{display:grid;gap:1rem;align-items:start}
  .form-row{grid-template-columns:1fr 1fr}
  .form-row-3{grid-template-columns:1fr 1fr 1fr}
  .form-group{margin-bottom:1rem;display:flex;flex-direction:column;min-width:0}
  .form-group label{display:flex;align-items:center;min-height:1.45em;font-weight:600;margin-bottom:.4rem;color:#444;font-size:14px;line-height:1.35}
  .form-group .req{color:#e74c3c}
  .form-group input,.form-group select,.form-group textarea{width:100%;min-height:46px;padding:.7rem 1rem;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:14px;background:#fff;transition:.2s;box-sizing:border-box}
  .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
  .form-group textarea{resize:vertical;min-height:90px}
  .form-group .help{font-size:14px;color:#888;margin-top:.3rem}

  .amount-input{position:relative;width:100%}
  .amount-input .symbol{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:16px;font-weight:700;color:var(--primary);pointer-events:none;line-height:1}
  .amount-input input{padding-left:2.4rem;font-size:16px;font-weight:700;color:var(--primary-dark);min-height:46px;width:100%}

  .pay-methods{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;align-items:stretch}
  .pay-method{position:relative;cursor:pointer;display:flex;min-width:0}
  .pay-method input{position:absolute;opacity:0;pointer-events:none}
  .pay-method label{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.4rem;padding:1rem .7rem;background:#fff;border:2px solid #eee;border-radius:10px;cursor:pointer;transition:.2s;font-size:14px;font-weight:600;color:#555;text-align:center;margin:0;width:100%;min-height:118px}
  .pay-method input:checked + label{border-color:var(--primary);background:#e8f5ef;color:var(--primary-dark)}
  .pay-method .ico{font-size:1.8rem;line-height:1}
  .pay-method small{display:block;line-height:1.3}

  .checkbox-row{display:flex;align-items:flex-start;gap:.6rem;padding:.55rem 0;cursor:pointer;margin:0}
  .checkbox-row input{width:18px;height:18px;margin-top:2px;flex-shrink:0;accent-color:var(--primary)}
  .checkbox-row span{font-size:14px;color:#444;line-height:1.4}

  .alert{padding:1rem 1.4rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500;animation:slideDown .4s ease}
  .alert.success{background:#e8f5ef;color:#1d4ed8;border-left:4px solid #2563eb}
  .alert.error{background:#fdecea;color:#c0392b;border-left:4px solid #e74c3c}
  @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

  /* WHY DONATE */
  .why-donate{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1.2rem;margin-top:2rem;align-items:stretch}
  .why-card{background:#fff;padding:1.5rem;border-radius:12px;box-shadow:var(--shadow);text-align:center;border-top:3px solid var(--primary);display:flex;flex-direction:column;align-items:center;height:100%}
  .why-card .ico{font-size:2.2rem;margin-bottom:.6rem;line-height:1}
  .why-card h4{color:var(--primary-dark);margin-bottom:.5rem;font-size:14px}
  .why-card p{color:var(--gray);font-size:14px;flex:1}

  /* BANK DETAILS */
  .bank-details{background:linear-gradient(135deg,#fffaf0,#fff);padding:2rem;border-radius:14px;border:2px dashed var(--accent);margin-bottom:2rem}
  .bank-details h3{color:var(--primary-dark);margin-bottom:1rem;font-size:16px}
  .bank-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start}
  .bank-grid h4{color:var(--accent-dark);margin-bottom:.6rem;font-size:14px}
  .bank-grid ul{list-style:none;padding:0;margin:0}
  .bank-grid li{padding:.45rem 0;border-bottom:1px dashed #ddd;font-size:14px;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;align-items:center}
  .bank-grid li span{text-align:left}
  .bank-grid li strong{color:var(--primary-dark);text-align:right}

  .submit-btn{width:100%;padding:.75rem;font-size:14px;font-weight:700;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:10px;cursor:pointer;transition:.25s;letter-spacing:.4px;text-transform:uppercase;box-shadow:0 8px 20px rgba(231,111,81,.3);font-family:var(--font-heading)}
  .submit-btn:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(231,111,81,.45)}

  @media(max-width:880px){
    .why-donate{grid-template-columns:repeat(2,minmax(0,1fr))}
    .impact-tiers{grid-template-columns:repeat(3,minmax(0,1fr))}
    .pay-methods{grid-template-columns:repeat(3,minmax(0,1fr))}
  }
  @media(max-width:780px){
    .donate-form-card{padding:1.5rem}
    .form-row,.form-row-3,.bank-grid{grid-template-columns:1fr}
    .impact-tiers,.pay-methods{grid-template-columns:repeat(2,minmax(0,1fr))}
    .stats-bar-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
    .section-toolbar{align-items:flex-start}
  }
  @media(max-width:480px){
    .why-donate,.impact-tiers,.pay-methods{grid-template-columns:1fr}
  }
</style>';

require __DIR__ . '/../includes/public_header.php';
?>

<!-- HERO -->
<section class="donate-hero" style="<?= e(site_bg_attr('banner_donate', 'linear-gradient(rgba(29,78,216,.88),rgba(26,46,53,.88))', 'images/donate-bg.jpg')) ?>">
  <div class="container">
    <span class="tag" style="background:rgba(244,162,97,.2);color:var(--accent)">✦ <?= strtoupper(e(t('nav_donate'))) ?></span>
    <h1 style="margin-top:.5rem"><?= e(t('donate_hero_title')) ?> ❤️</h1>
    <p><?= e(t('donate_hero_text')) ?></p>
  </div>
</section>

<!-- LIVE STATS -->
<section class="stats-bar">
  <div class="container">
    <div class="stats-bar-grid">
      <div><h3><?= number_format($total_donors_completed) ?>+</h3><p>Donors So Far</p></div>
      <div><h3>₹<?= number_format($total_raised_inr, 0) ?></h3><p>Raised in India</p></div>
      <div><h3>£<?= number_format($total_raised_gbp, 0) ?></h3><p>Raised in UK</p></div>
      <div><h3>2,500+</h3><p>Lives Touched</p></div>
    </div>
  </div>
</section>

<!-- DONATION FORM -->
<section class="sec-cream" id="donation-form">
  <div class="container">

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>" style="max-width:1000px;margin:0 auto 1.5rem"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>api/submit_donation.php" method="post" class="donate-form-card" id="donateForm" enctype="application/x-www-form-urlencoded">

      <!-- AMOUNT SECTION -->
      <div class="form-section">
        <div class="section-toolbar">
          <h3>💝 <?= e(t('choose_amount')) ?></h3>
          <div class="currency-toggle" role="group" aria-label="Currency">
            <button type="button" class="curr-btn active" data-curr="INR">🇮🇳 INR (₹)</button>
            <button type="button" class="curr-btn" data-curr="GBP">🇬🇧 GBP (£)</button>
          </div>
        </div>
        <input type="hidden" name="currency" id="currencyInput" value="INR">

        <!-- INR tiers -->
        <div class="impact-tiers tier-set" data-curr="INR">
          <div class="tier" data-amount="500"><div class="tier-smile" aria-hidden="true">😊</div><div class="amount">₹500</div><div class="desc"><?= e(t('sponsor_meals')) ?></div></div>
          <div class="tier" data-amount="1000"><div class="tier-smile" aria-hidden="true">😄</div><div class="amount">₹1,000</div><div class="desc"><?= e(t('sponsor_books')) ?></div></div>
          <div class="tier popular selected" data-amount="2500"><div class="ribbon">★ <?= e(t('most_popular')) ?></div><div class="tier-smile" aria-hidden="true">🤗</div><div class="amount">₹2,500</div><div class="desc"><?= e(t('sponsor_education')) ?></div></div>
          <div class="tier" data-amount="5000"><div class="tier-smile" aria-hidden="true">🥰</div><div class="amount">₹5,000</div><div class="desc"><?= e(t('sponsor_hostel')) ?></div></div>
          <div class="tier" data-amount="10000"><div class="tier-smile" aria-hidden="true">🤩</div><div class="amount">₹10,000</div><div class="desc"><?= e(t('sponsor_bible')) ?></div></div>
        </div>

        <!-- GBP tiers -->
        <div class="impact-tiers tier-set" data-curr="GBP" style="display:none">
          <div class="tier" data-amount="10"><div class="tier-smile" aria-hidden="true">😊</div><div class="amount">£10</div><div class="desc"><?= e(t('sponsor_books')) ?></div></div>
          <div class="tier popular selected" data-amount="25"><div class="ribbon">★ <?= e(t('most_popular')) ?></div><div class="tier-smile" aria-hidden="true">😄</div><div class="amount">£25</div><div class="desc"><?= e(t('sponsor_education')) ?></div></div>
          <div class="tier" data-amount="50"><div class="tier-smile" aria-hidden="true">🤗</div><div class="amount">£50</div><div class="desc"><?= e(t('sponsor_hostel')) ?></div></div>
          <div class="tier" data-amount="100"><div class="tier-smile" aria-hidden="true">🥰</div><div class="amount">£100</div><div class="desc"><?= e(t('sponsor_bible')) ?></div></div>
          <div class="tier" data-amount="250"><div class="tier-smile" aria-hidden="true">🤩</div><div class="amount">£250</div><div class="desc">Supports a full classroom</div></div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label><?= e(t('choose_custom')) ?> <span class="req">*</span></label>
            <div class="amount-input">
              <span class="symbol" id="currSym">₹</span>
              <input type="number" name="amount" id="amountInput" value="2500" min="1" step="any" required placeholder="0">
            </div>
          </div>
          <div class="form-group">
            <label><?= e(t('donation_type')) ?></label>
            <select name="donation_type" id="donationType">
              <option value="one-time"><?= e(t('one_time')) ?></option>
              <option value="monthly"><?= e(t('monthly')) ?> 🔁</option>
              <option value="yearly"><?= e(t('yearly')) ?> 🔁</option>
            </select>
          </div>
        </div>

        <div id="recurringExtras" style="display:none;background:#fffaf0;border:2px dashed #f4a261;border-radius:10px;padding:1rem 1.2rem;margin-bottom:1rem">
          <div class="form-group" style="margin-bottom:0">
            <label>🔁 Recurring Frequency</label>
            <select name="frequency">
              <option value="weekly">Weekly</option>
              <option value="monthly" selected>Monthly</option>
              <option value="quarterly">Quarterly (every 3 months)</option>
              <option value="yearly">Yearly</option>
            </select>
            <p style="font-size:14px;color:#5b4a2c;margin-top:.4rem">
              💡 You'll receive a personal link to <strong>manage, pause, or cancel</strong> anytime.
              We'll also send a friendly reminder 3 days before each charge.
            </p>
          </div>
        </div>

        <div class="form-group">
          <label><?= e(t('donate_purpose')) ?></label>
          <select name="purpose">
            <option value="Where Most Needed"><?= e(t('where_needed')) ?></option>
            <?php foreach ($programs as $p): ?>
              <option value="<?= e(tr_field($p, 'title')) ?>"><?= e(tr_field($p, 'title')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- DONOR INFO -->
      <div class="form-section">
        <h3><?= e(t('donor_info')) ?></h3>
        <div class="form-row">
          <div class="form-group">
            <label><?= e(t('full_name')) ?> <span class="req">*</span></label>
            <input type="text" name="donor_name" required>
          </div>
          <div class="form-group">
            <label><?= e(t('email')) ?> <span class="req">*</span></label>
            <input type="email" name="email" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= e(t('phone_number')) ?> <span class="req">*</span></label>
            <input type="tel" name="phone" required>
          </div>
          <div class="form-group">
            <label><?= e(t('country')) ?></label>
            <select name="country" id="countrySelect">
              <option>India</option>
              <option>United Kingdom</option>
              <option>USA</option>
              <option>Australia</option>
              <option>Canada</option>
              <option>UAE</option>
              <option>Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label><?= e(t('address')) ?></label>
          <textarea name="address" rows="2"></textarea>
        </div>
        <div class="form-row-3">
          <div class="form-group"><label><?= e(t('city')) ?></label><input type="text" name="city"></div>
          <div class="form-group"><label><?= e(t('state')) ?></label><input type="text" name="state"></div>
          <div class="form-group"><label><?= e(t('pincode')) ?></label><input type="text" name="pincode"></div>
        </div>
        <div class="form-group" id="panField">
          <label><?= e(t('pan_number')) ?></label>
          <input type="text" name="pan_number" maxlength="10" placeholder="ABCDE1234F" style="text-transform:uppercase">
          <p class="help">Required for 80G tax-exemption certificate (India). Leave blank if not applicable.</p>
        </div>
      </div>

      <!-- PAYMENT METHOD -->
      <div class="form-section">
        <h3><?= e(t('payment_info')) ?></h3>

        <?php if ($gateway_mode === 'sandbox' && $gateways): ?>
          <div style="background:#fef7e0;padding:.6rem 1rem;border-left:3px solid #d4a017;border-radius:6px;color:#5b4a2c;font-size:14px;margin-bottom:1rem">
            🧪 <strong>Sandbox/Test Mode</strong> — no real money will be charged. Use gateway test cards.
          </div>
        <?php endif; ?>

        <div class="pay-methods">
          <!-- Live gateway options first -->
          <?php foreach ($gateways as $gw_id => $gw):
            $checked = $gw_id === 'razorpay' ? 'checked' : ''; ?>
          <div class="pay-method"><input type="radio" name="payment_method" value="<?= $gw_id ?>" id="pm-<?= $gw_id ?>" <?= $checked ?> data-live="1"><label for="pm-<?= $gw_id ?>"><span class="ico"><?= $gw['icon'] ?></span><?= e($gw['name']) ?><br><small style="font-weight:400;color:#888;font-size:14px"><?= e($gw['desc']) ?></small></label></div>
          <?php endforeach; ?>

          <!-- Manual methods (always available) -->
          <div class="pay-method"><input type="radio" name="payment_method" value="upi" id="pm-upi" <?= !$gateways ? 'checked' : '' ?>><label for="pm-upi"><span class="ico">📱</span>UPI<br><small style="font-weight:400;color:#888;font-size:14px">Pay later manually</small></label></div>
          <div class="pay-method"><input type="radio" name="payment_method" value="bank_transfer" id="pm-bank"><label for="pm-bank"><span class="ico">🏦</span>Bank Transfer<br><small style="font-weight:400;color:#888;font-size:14px">Pay later manually</small></label></div>
          <div class="pay-method"><input type="radio" name="payment_method" value="cheque" id="pm-ch"><label for="pm-ch"><span class="ico">📝</span>Cheque / DD<br><small style="font-weight:400;color:#888;font-size:14px">Send via post</small></label></div>
        </div>

        <div class="form-group" id="manualTxnField" style="margin-top:1rem">
          <label>Transaction / Reference ID <span style="color:var(--gray);font-weight:400">(if already paid)</span></label>
          <input type="text" name="transaction_id" placeholder="Leave blank if paying after submitting form">
          <p class="help">For UPI / Bank Transfer: if you've already paid, enter the transaction ID here.</p>
        </div>

        <div class="form-group">
          <label><?= e(t('donor_message')) ?></label>
          <textarea name="message" rows="2" placeholder="Share a message with us..."></textarea>
        </div>

        <label class="checkbox-row"><input type="checkbox" name="is_anonymous" value="1"><span><?= e(t('is_anonymous')) ?></span></label>
        <label class="checkbox-row"><input type="checkbox" name="receipt_required" value="1" checked><span><?= e(t('receipt_required')) ?> (80G / Gift Aid)</span></label>
        <label class="checkbox-row"><input type="checkbox" name="newsletter_optin" value="1" checked><span><?= e(t('newsletter_optin')) ?></span></label>
      </div>

      <button type="submit" class="submit-btn" id="submitBtn">❤️ <?= e(t('complete_donation')) ?> →</button>
      <p style="text-align:center;margin-top:1rem;color:var(--gray);font-size:14px">🔒 Your information is safe. We use industry-standard encryption.</p>
    </form>

  </div>
</section>

<!-- BANK DETAILS -->
<section class="sec-sand">
  <div class="container" style="max-width:1000px">
    <div class="bank-details">
      <h3><?= e(t('bank_details')) ?> &amp; UPI</h3>
      <p style="color:var(--gray);font-size:14px;margin-bottom:1.2rem">If you prefer to make a direct bank transfer or use UPI, please use the details below:</p>
      <div class="bank-grid">
        <div>
          <h4>🇮🇳 For Indian Donations</h4>
          <ul>
            <li><span><?= e(t('bank_account_name')) ?></span><strong>Sharan Foundation</strong></li>
            <li><span><?= e(t('bank_account_number')) ?></span><strong>1234567890123</strong></li>
            <li><span><?= e(t('bank_ifsc')) ?></span><strong>SBIN0001234</strong></li>
            <li><span><?= e(t('bank_name')) ?></span><strong>State Bank of India</strong></li>
            <li><span><?= e(t('bank_branch')) ?></span><strong>Hyderabad</strong></li>
            <li><span><?= e(t('upi_id')) ?></span><strong style="color:var(--accent-dark)">sharanfoundation@upi</strong></li>
          </ul>
        </div>
        <div>
          <h4>🇬🇧 For UK Donations</h4>
          <ul>
            <li><span><?= e(t('bank_account_name')) ?></span><strong>Sharan Foundation UK</strong></li>
            <li><span><?= e(t('bank_account_number')) ?></span><strong>12345678</strong></li>
            <li><span>Sort Code</span><strong>12-34-56</strong></li>
            <li><span><?= e(t('bank_name')) ?></span><strong>Barclays</strong></li>
            <li><span><?= e(t('bank_branch')) ?></span><strong>London</strong></li>
            <li><span>Charity No.</span><strong>1234567</strong></li>
          </ul>
        </div>
      </div>
      <p style="margin-top:1.5rem;font-size:14px;color:#5b4a2c;background:#fffaf0;padding:.7rem 1rem;border-radius:6px">
        💡 <strong>After making payment</strong>, please fill out the form above with your transaction ID so we can send your receipt.
      </p>
    </div>
  </div>
</section>

<!-- WHY DONATE -->
<section class="sec-lilac">
  <div class="container">
    <div class="section-head">
      <span class="tag">✦ TRUST &amp; TRANSPARENCY</span>
      <h2><?= e(t('why_donate')) ?></h2>
    </div>
    <div class="why-donate">
      <div class="why-card"><div class="ico">🧾</div><h4>Tax Benefits</h4><p>80G certificate in India, Gift Aid in the UK — every donation eligible for tax benefits.</p></div>
      <div class="why-card"><div class="ico">📊</div><h4>100% Transparency</h4><p>Annual reports, audited accounts, and detailed receipts for every donation.</p></div>
      <div class="why-card"><div class="ico">💯</div><h4>Direct Impact</h4><p>Every rupee and pound goes directly to programs — minimal overhead.</p></div>
      <div class="why-card"><div class="ico">🌐</div><h4>Trusted Globally</h4><p>Registered charity in India &amp; UK, serving since 2008 with proven impact.</p></div>
    </div>
  </div>
</section>

<script>
// Tier selection
document.querySelectorAll('.tier').forEach(t => {
  t.addEventListener('click', () => {
    document.querySelectorAll('.tier-set').forEach(s => {
      if (s.style.display !== 'none') s.querySelectorAll('.tier').forEach(x => x.classList.remove('selected'));
    });
    t.classList.add('selected');
    document.getElementById('amountInput').value = t.dataset.amount;
  });
});

// Currency toggle
document.querySelectorAll('.curr-btn').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.curr-btn').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    const curr = b.dataset.curr;
    document.getElementById('currencyInput').value = curr;
    document.getElementById('currSym').textContent = curr === 'INR' ? '₹' : (curr === 'GBP' ? '£' : '$');
    document.querySelectorAll('.tier-set').forEach(s => {
      s.style.display = s.dataset.curr === curr ? '' : 'none';
    });
    // Set first selected tier amount
    const firstSelected = document.querySelector('.tier-set[data-curr="'+curr+'"] .tier.selected, .tier-set[data-curr="'+curr+'"] .tier.popular');
    if (firstSelected) document.getElementById('amountInput').value = firstSelected.dataset.amount;
  });
});

// Hide PAN field if not India
document.getElementById('countrySelect').addEventListener('change', e => {
  document.getElementById('panField').style.display = e.target.value === 'India' ? '' : 'none';
});

// Auto-switch currency when country changes
document.getElementById('countrySelect').addEventListener('change', e => {
  if (e.target.value === 'United Kingdom') {
    document.querySelector('.curr-btn[data-curr="GBP"]').click();
  } else if (e.target.value === 'India') {
    document.querySelector('.curr-btn[data-curr="INR"]').click();
  }
});

// Toggle recurring frequency picker
const donType = document.getElementById('donationType');
const recExtras = document.getElementById('recurringExtras');
const freqSelect = recExtras.querySelector('select[name="frequency"]');
function syncRecurring() {
  const isRec = donType.value !== 'one-time';
  recExtras.style.display = isRec ? '' : 'none';
  if (donType.value === 'monthly') freqSelect.value = 'monthly';
  else if (donType.value === 'yearly') freqSelect.value = 'yearly';
}
donType.addEventListener('change', syncRecurring);
syncRecurring();

// Toggle manual transaction-ID field based on payment method
const manualTxnField = document.getElementById('manualTxnField');
function syncManualField() {
  const selected = document.querySelector('input[name="payment_method"]:checked');
  const isLive = selected && selected.dataset.live === '1';
  manualTxnField.style.display = isLive ? 'none' : '';
}
document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', syncManualField));
syncManualField();

// ============================================================
// PAYMENT GATEWAY ORCHESTRATOR
// ============================================================
const form = document.getElementById('donateForm');
const submitBtn = document.getElementById('submitBtn');
const BASE = '<?= BASE_URL ?>';

form.addEventListener('submit', async (e) => {
  const selected = document.querySelector('input[name="payment_method"]:checked');
  if (!selected) return;
  const isLive = selected.dataset.live === '1';

  // Manual payment methods → use the original submit_donation.php flow (no JS interception)
  if (!isLive) return;

  // Live gateway: intercept and use the AJAX init endpoint
  e.preventDefault();
  if (!form.reportValidity()) return;

  submitBtn.disabled = true;
  const originalText = submitBtn.textContent;
  submitBtn.textContent = '⏳ Initialising payment...';

  try {
    const fd = new FormData(form);
    fd.set('gateway', selected.value);   // explicit gateway value

    const res = await fetch(BASE + 'api/payment/init.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.ok) {
      alert('❌ ' + (data.error || 'Could not start payment'));
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
      return;
    }

    // Branch by gateway
    if (data.gateway === 'razorpay') {
      submitBtn.textContent = '🚀 Opening Razorpay...';
      await loadScript('https://checkout.razorpay.com/v1/checkout.js');
      const rzp = new Razorpay({
        key: data.key_id,
        order_id: data.order_id,
        amount: data.amount,
        currency: data.currency,
        name: data.name,
        description: data.description,
        prefill: data.prefill,
        theme: { color: '#2563eb' },
        handler: function (response) {
          // Post the response to our success handler
          const tempForm = document.createElement('form');
          tempForm.method = 'POST';
          tempForm.action = data.callback_url;
          for (const k of ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature']) {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = k; i.value = response[k] || '';
            tempForm.appendChild(i);
          }
          document.body.appendChild(tempForm);
          tempForm.submit();
        },
        modal: {
          ondismiss: function () {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            alert('Payment cancelled.');
          }
        }
      });
      rzp.open();
    }
    else if (data.gateway === 'stripe' || data.gateway === 'paypal') {
      submitBtn.textContent = '🚀 Redirecting...';
      window.location.href = data.redirect_url;
    }
    else {
      alert('Unsupported gateway: ' + data.gateway);
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }
  } catch (err) {
    alert('❌ ' + err.message);
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
});

function loadScript(src) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve();
    const s = document.createElement('script');
    s.src = src; s.onload = resolve; s.onerror = () => reject(new Error('Failed to load ' + src));
    document.head.appendChild(s);
  });
}
</script>

<?php require __DIR__ . '/../includes/public_footer.php'; ?>
