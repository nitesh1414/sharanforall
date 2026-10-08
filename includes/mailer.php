<?php
/**
 * Sharan Foundation — Email System (PHPMailer-powered)
 *
 * SMTP credentials are stored in the `settings` table and editable
 * from Admin → Site Settings → 📧 Email (SMTP).
 *
 * Falls back to PHP's mail() if PHPMailer/SMTP isn't configured.
 * All attempts are logged to logs/mail.log.
 */

// Load PHPMailer via shared bootstrap (Composer if present, else bundled copy)
require_once __DIR__ . '/bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

define('MAIL_LOG_FILE', __DIR__ . '/../logs/mail.log');

/**
 * Get SMTP/email configuration from DB settings.
 */
function mail_config() {
    global $pdo;
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    try {
        $cfg = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch() ?: [];
    } catch (Throwable $e) {
        $cfg = [];
    }
    // Defaults
    $cfg += [
        'smtp_host' => '', 'smtp_port' => 587, 'smtp_username' => '', 'smtp_password' => '',
        'smtp_encryption' => 'tls', 'smtp_from_email' => 'noreply@sharanforall.org',
        'smtp_from_name' => 'Sharan Foundation',
        'admin_notify_email' => 'admin@sharanforall.org',
    ];
    return $cfg;
}

/**
 * Send email via PHPMailer (SMTP) if configured; else fallback to mail().
 * @param string|array $attachments  Optional file path(s) to attach
 * Returns [bool $sent, string $error_message]
 */
function send_mail($to, $subject, $html_body, $reply_to = null, $attachments = [])
{
    $cfg = mail_config();
    $use_smtp = !empty($cfg['smtp_host']) && !empty($cfg['smtp_username']);

    if (!is_array($attachments)) $attachments = [$attachments];
    $attachments = array_filter($attachments, fn($p) => $p && is_file($p));

    $sent = false;
    $error = '';

    if ($use_smtp && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        // ============ PHPMailer SMTP path ============
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['smtp_host'];
            $mail->Port       = (int)$cfg['smtp_port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['smtp_username'];
            $mail->Password   = $cfg['smtp_password'];
            $enc              = strtolower($cfg['smtp_encryption']);
            if ($enc === 'ssl')      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            elseif ($enc === 'tls')  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            else                     $mail->SMTPSecure = false;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 15;

            $mail->setFrom($cfg['smtp_from_email'], $cfg['smtp_from_name']);
            $mail->addAddress($to);
            if ($reply_to) $mail->addReplyTo($reply_to);
            foreach ($attachments as $att) $mail->addAttachment($att);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html_body;
            $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html_body));

            $sent = $mail->send();
            if (!$sent) $error = $mail->ErrorInfo;
        } catch (MailException $e) {
            $sent = false;
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $sent = false;
            $error = $e->getMessage();
        }
    } else {
        // ============ Fallback: PHP mail() — supports attachments via MIME ============
        $boundary = md5(uniqid('', true));
        $headers   = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "From: " . mb_encode_mimeheader($cfg['smtp_from_name']) . " <{$cfg['smtp_from_email']}>";
        if ($reply_to) $headers[] = "Reply-To: {$reply_to}";
        $headers[] = "X-Mailer: sharanforall-Mailer/2.1";

        if ($attachments) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $html_body . "\r\n\r\n";
            foreach ($attachments as $att) {
                $fname = basename($att);
                $content = chunk_split(base64_encode(file_get_contents($att)));
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: application/pdf; name=\"{$fname}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$fname}\"\r\n\r\n";
                $body .= $content . "\r\n";
            }
            $body .= "--{$boundary}--";
        } else {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $body = $html_body;
        }

        try {
            $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
            if (!$sent) $error = 'PHP mail() failed — SMTP not configured.';
        } catch (Throwable $e) {
            $sent = false;
            $error = $e->getMessage();
        }
    }

    log_mail($to, $subject, $sent, $use_smtp, $error);
    return [$sent, $error];
}

function log_mail($to, $subject, $sent, $used_smtp = false, $error = '') {
    $dir = dirname(MAIL_LOG_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $method = $used_smtp ? 'SMTP' : 'mail()';
    $status = $sent ? "SENT ($method)" : "FAILED ($method)" . ($error ? " - $error" : '');
    $line = sprintf("[%s] %s | To: %s | Subject: %s\n", date('Y-m-d H:i:s'), $status, $to, $subject);
    @file_put_contents(MAIL_LOG_FILE, $line, FILE_APPEND);
}

/**
 * Branded HTML email template.
 */
function email_template($title, $body_html, $cta_label = null, $cta_url = null) {
    $cta = '';
    if ($cta_label && $cta_url) {
        $cta = '<tr><td style="padding:0 30px 30px;text-align:center"><a href="' . htmlspecialchars($cta_url) . '" style="display:inline-block;background:#f4a261;color:#ffffff;padding:14px 28px;border-radius:50px;text-decoration:none;font-weight:600;font-size:15px">' . htmlspecialchars($cta_label) . '</a></td></tr>';
    }
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f1f4f6;font-family:Arial,Helvetica,sans-serif;color:#333">
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f1f4f6;padding:30px 10px">
  <tr><td align="center">
    <table cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.08)">
      <tr><td style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:25px 30px;text-align:center;color:#fff">
        <h1 style="margin:0;font-size:22px;font-weight:700">Sharan Foundation</h1>
        <p style="margin:5px 0 0;font-size:14px;opacity:.9;letter-spacing:2px">HOPE • CARE • TRANSFORM</p>
      </td></tr>
      <tr><td style="padding:30px;color:#0d2940"><h2 style="margin:0 0 15px;color:#2563eb;font-size:20px">' . htmlspecialchars($title) . '</h2>' . $body_html . '</td></tr>
      ' . $cta . '
      <tr><td style="background:#0a1c2e;color:#8da4a8;padding:20px 30px;text-align:center;font-size:14px">
        <p style="margin:0">© ' . date('Y') . ' Sharan Foundation • Serving in India 🇮🇳 & UK 🇬🇧</p>
        <p style="margin:5px 0 0;opacity:.7">This is an automated notification.</p>
      </td></tr>
    </table>
  </td></tr>
</table></body></html>';
}

// ============ NOTIFICATION HELPERS ============

function notify_new_volunteer($data) {
    $cfg = mail_config();
    $rows = '';
    foreach ([
        'Full Name'        => $data['full_name'],
        'Email'            => $data['email'],
        'Phone'            => $data['phone'],
        'Country / City'   => trim(($data['country']??'') . ' / ' . ($data['city']??''), ' /'),
        'Age / Gender'     => trim(($data['age']??'') . ' / ' . ($data['gender']??''), ' /'),
        'Occupation'       => $data['occupation'] ?? '',
        'Area of Interest' => $data['area_of_interest'],
        'Availability'     => $data['availability'] ?? '',
        'Skills'           => $data['skills'] ?? '',
        'Experience'       => $data['experience'] ?? '',
        'Motivation'       => $data['motivation'] ?? '',
    ] as $label => $val) {
        if ($val === '' || $val === null) continue;
        $rows .= '<tr><td style="padding:8px 12px;background:#f9fafb;font-weight:600;width:160px;vertical-align:top;border-bottom:1px solid #eee">' . htmlspecialchars($label) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee">' . nl2br(htmlspecialchars($val)) . '</td></tr>';
    }
    $body  = '<p>A new volunteer application has just been submitted on your website.</p>';
    $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin-top:15px;font-size:14px">' . $rows . '</table>';
    send_mail($cfg['admin_notify_email'], 'New Volunteer Application — ' . $data['full_name'],
              email_template('🤝 New Volunteer Application', $body, 'View in Admin Panel', ADMIN_URL . 'volunteers.php'),
              $data['email']);

    $reply  = '<p>Dear ' . htmlspecialchars($data['full_name']) . ',</p>';
    $reply .= '<p>Thank you so much for offering your time and heart to <strong>Sharan Foundation</strong>! 🙏</p>';
    $reply .= '<p>We have received your volunteer application and our team will review it shortly. You can expect to hear from us within <strong>5-7 working days</strong>.</p>';
    $reply .= '<p>In the meantime, feel free to explore our programs and stories on our website.</p>';
    $reply .= '<p style="margin-top:20px">With gratitude,<br><strong>The Sharan Foundation Team</strong></p>';
    send_mail($data['email'], 'Thank you for applying to volunteer — Sharan Foundation',
              email_template('🙏 Thank You for Volunteering!', $reply, 'Visit Our Website', BASE_URL));
}

function notify_new_partner($data) {
    $cfg = mail_config();
    $rows = '';
    foreach ([
        'Organization'      => $data['org_name'],
        'Type'              => $data['org_type'],
        'Contact Person'    => $data['contact_person'],
        'Designation'       => $data['designation'] ?? '',
        'Email'             => $data['email'],
        'Phone'             => $data['phone'],
        'Country'           => $data['country'] ?? '',
        'Website'           => $data['website'] ?? '',
        'Partnership Type'  => $data['partnership_type'] ?? '',
        'Budget Range'      => $data['budget_range'] ?? '',
        'Programs of Interest' => $data['programs_of_interest'] ?? '',
        'Proposal / Message' => $data['proposal'] ?? '',
    ] as $label => $val) {
        if ($val === '' || $val === null) continue;
        $rows .= '<tr><td style="padding:8px 12px;background:#f9fafb;font-weight:600;width:160px;vertical-align:top;border-bottom:1px solid #eee">' . htmlspecialchars($label) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee">' . nl2br(htmlspecialchars($val)) . '</td></tr>';
    }
    $body = '<p>A new partnership inquiry has just been submitted.</p><table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin-top:15px;font-size:14px">' . $rows . '</table>';
    send_mail($cfg['admin_notify_email'], 'New Partnership Inquiry — ' . $data['org_name'],
              email_template('🏢 New Partnership Inquiry', $body, 'View in Admin Panel', ADMIN_URL . 'partners.php'),
              $data['email']);

    $reply  = '<p>Dear ' . htmlspecialchars($data['contact_person']) . ',</p>';
    $reply .= '<p>Thank you for your interest in partnering with <strong>Sharan Foundation</strong>! 🤝</p>';
    $reply .= '<p>We are excited to explore how we can work together to bring hope and transformation. A partnership coordinator will reach out to you within <strong>48 hours</strong>.</p>';
    $reply .= '<p style="margin-top:20px">Warm regards,<br><strong>The Sharan Foundation Partnerships Team</strong></p>';
    send_mail($data['email'], 'Thank you for partnering with us — Sharan Foundation',
              email_template('🤝 Thank You for Your Partnership Interest!', $reply, 'Visit Our Website', BASE_URL));
}

function notify_new_contact($data) {
    $cfg = mail_config();
    $rows = '';
    foreach ([
        'Name' => $data['name'], 'Email' => $data['email'], 'Phone' => $data['phone'] ?? '',
        'Interest' => $data['interest'] ?? '', 'Office' => $data['office'] ?? '',
        'Message' => $data['message'],
    ] as $label => $val) {
        if ($val === '' || $val === null) continue;
        $rows .= '<tr><td style="padding:8px 12px;background:#f9fafb;font-weight:600;width:130px;vertical-align:top;border-bottom:1px solid #eee">' . htmlspecialchars($label) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee">' . nl2br(htmlspecialchars($val)) . '</td></tr>';
    }
    $body = '<p>A new contact message has just arrived.</p><table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin-top:15px;font-size:14px">' . $rows . '</table>';
    send_mail($cfg['admin_notify_email'], 'New Contact Message — ' . $data['name'],
              email_template('✉️ New Contact Message', $body, 'View in Admin Panel', ADMIN_URL . 'contacts.php'),
              $data['email']);

    $reply  = '<p>Dear ' . htmlspecialchars($data['name']) . ',</p>';
    $reply .= '<p>Thank you for reaching out to <strong>Sharan Foundation</strong>! 🙏</p>';
    $reply .= '<p>We have received your message and will get back to you as soon as possible.</p>';
    $reply .= '<p style="margin-top:20px">Blessings,<br><strong>The Sharan Foundation Team</strong></p>';
    send_mail($data['email'], 'We received your message — Sharan Foundation',
              email_template('🙏 We Received Your Message', $reply));
}

function notify_new_subscriber($email) {
    $body  = '<p>Welcome to the <strong>Sharan Foundation</strong> family! 💌</p>';
    $body .= '<p>You will now receive monthly updates with stories of hope, project news, and ways you can make a difference.</p>';
    $body .= '<p style="margin-top:20px">With gratitude,<br><strong>The Sharan Foundation Team</strong></p>';
    send_mail($email, 'Welcome to Sharan Foundation Newsletter',
              email_template('🎉 Welcome to Our Newsletter!', $body, 'Visit Our Website', BASE_URL));
}

/**
 * Donation notifications — sends 2 emails:
 *   1. Admin alert with full details + receipt info
 *   2. Donor thank-you email with payment instructions
 */
function notify_new_donation($data) {
    $cfg = mail_config();
    $sym = $data['currency'] === 'INR' ? '₹' : ($data['currency'] === 'GBP' ? '£' : '$');
    $amount_display = $sym . number_format((float)$data['amount'], 2);

    // ---------- 1. Admin notification ----------
    $rows = '';
    foreach ([
        'Donor Name'      => $data['donor_name'] . ($data['is_anonymous'] ? ' (anonymous — hide on public)' : ''),
        'Email'           => $data['email'],
        'Phone'           => $data['phone'],
        'Address'         => trim(($data['address'] ?? '') . ', ' . ($data['city'] ?? '') . ', ' . ($data['state'] ?? '') . ' - ' . ($data['pincode'] ?? '') . ', ' . ($data['country'] ?? ''), ', -'),
        'PAN Number'      => $data['pan_number'] ?? '',
        'Amount'          => $amount_display,
        'Currency'        => $data['currency'],
        'Donation Type'   => ucfirst($data['donation_type']),
        'Purpose / Cause' => $data['purpose'] ?? 'Where Most Needed',
        'Payment Method'  => strtoupper(str_replace('_', ' ', $data['payment_method'])),
        'Transaction ID'  => $data['transaction_id'] ?? '(none — pending verification)',
        'Status'          => strtoupper($data['payment_status']),
        'Donor Message'   => $data['message'] ?? '',
    ] as $label => $val) {
        if ($val === '' || $val === null) continue;
        $rows .= '<tr><td style="padding:8px 12px;background:#f9fafb;font-weight:600;width:170px;vertical-align:top;border-bottom:1px solid #eee">' . htmlspecialchars($label) . '</td><td style="padding:8px 12px;border-bottom:1px solid #eee">' . nl2br(htmlspecialchars($val)) . '</td></tr>';
    }
    $body  = '<p>🎉 A new donation has just been received on your website.</p>';
    $body .= '<p style="font-size:28px;color:#2563eb;text-align:center;margin:18px 0;font-weight:800">' . $amount_display . '</p>';
    $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin-top:15px;font-size:14px">' . $rows . '</table>';
    send_mail($cfg['admin_notify_email'], 'New Donation — ' . $amount_display . ' from ' . $data['donor_name'],
              email_template('💝 New Donation Received', $body, 'View in Admin Panel', ADMIN_URL . 'donations.php'),
              $data['email']);

    // ---------- 2. Donor thank-you ----------
    $reply  = '<p>Dear ' . htmlspecialchars($data['donor_name']) . ',</p>';
    $reply .= '<p>Thank you so much for your generous donation to <strong>Sharan Foundation</strong>! 🙏</p>';
    $reply .= '<p style="font-size:24px;color:#2563eb;text-align:center;margin:20px 0;font-weight:800">' . $amount_display . '</p>';

    $reply .= '<p>Here are the details of your donation:</p>';
    $reply .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:15px 0;font-size:14px;background:#f9fafb;border-radius:8px;overflow:hidden">';
    $reply .= '<tr><td style="padding:8px 12px;font-weight:600;width:160px">Donation Type</td><td style="padding:8px 12px">' . htmlspecialchars(ucfirst($data['donation_type'])) . '</td></tr>';
    $reply .= '<tr><td style="padding:8px 12px;font-weight:600">Purpose</td><td style="padding:8px 12px">' . htmlspecialchars($data['purpose'] ?? 'Where Most Needed') . '</td></tr>';
    $reply .= '<tr><td style="padding:8px 12px;font-weight:600">Payment Method</td><td style="padding:8px 12px">' . htmlspecialchars(strtoupper(str_replace('_', ' ', $data['payment_method']))) . '</td></tr>';
    $reply .= '</table>';

    // Payment instructions based on method
    if (in_array($data['payment_method'], ['bank_transfer', 'cheque', 'cash', 'upi'])) {
        $reply .= '<p><strong>Next step — Please complete your payment using the details below:</strong></p>';
        $reply .= '<div style="background:#fffaf0;border-left:4px solid #f4a261;padding:15px 20px;margin:15px 0;border-radius:6px">';
        if ($data['currency'] === 'INR') {
            $reply .= '<p style="margin:0 0 8px"><strong>🇮🇳 For Indian donations:</strong></p>';
            $reply .= '<p style="margin:0;font-size:13px;line-height:1.8">';
            $reply .= '<strong>Account Name:</strong> Sharan Foundation<br>';
            $reply .= '<strong>Account No:</strong> 1234567890123<br>';
            $reply .= '<strong>IFSC Code:</strong> SBIN0001234<br>';
            $reply .= '<strong>Bank:</strong> State Bank of India, Hyderabad<br>';
            $reply .= '<strong>UPI ID:</strong> sharanfoundation@upi';
            $reply .= '</p>';
        } else {
            $reply .= '<p style="margin:0 0 8px"><strong>🇬🇧 For UK donations:</strong></p>';
            $reply .= '<p style="margin:0;font-size:13px;line-height:1.8">';
            $reply .= '<strong>Account Name:</strong> Sharan Foundation UK<br>';
            $reply .= '<strong>Account No:</strong> 12345678<br>';
            $reply .= '<strong>Sort Code:</strong> 12-34-56<br>';
            $reply .= '<strong>Bank:</strong> Barclays, London';
            $reply .= '</p>';
        }
        $reply .= '<p style="margin:10px 0 0;font-size:13px;color:#5b4a2c"><strong>Please use this reference:</strong> DONATION-' . substr(md5($data['email'] . time()), 0, 8) . '</p>';
        $reply .= '</div>';
        $reply .= '<p style="font-size:13px;color:#666">Once payment is received and verified, we will email you a receipt (with 80G / Gift Aid eligibility where applicable).</p>';
    } else {
        $reply .= '<p>Your payment will be processed shortly. A receipt will be emailed to you upon confirmation.</p>';
    }

    if (!empty($data['receipt_required'])) {
        $reply .= '<p style="font-size:13px;color:#666"><strong>📄 Tax receipt:</strong> Yes, we will send a tax-deductible receipt for your records.</p>';
    }

    $reply .= '<p style="margin-top:20px"><em>"Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver." — 2 Corinthians 9:7</em></p>';
    $reply .= '<p style="margin-top:20px">With deep gratitude,<br><strong>The Sharan Foundation Team</strong><br>India 🇮🇳 & United Kingdom 🇬🇧</p>';

    send_mail($data['email'], 'Thank you for your donation — Sharan Foundation',
              email_template('🙏 Thank You for Your Generous Gift!', $reply, 'Visit Our Website', BASE_URL));
}

// ============================ RECURRING DONATION EMAILS ============================

/**
 * Sent when a recurring donation profile is set up.
 */
function notify_recurring_started($r) {
    $sym = $r['currency'] === 'INR' ? '₹' : ($r['currency'] === 'GBP' ? '£' : '$');
    $amount = $sym . number_format($r['amount'], 0);
    $manage_url = BASE_URL . 'pages/manage-donation.php?token=' . $r['manage_token'];

    $body  = '<p>Dear ' . htmlspecialchars($r['donor_name']) . ',</p>';
    $body .= '<p>Thank you for setting up a <strong>' . htmlspecialchars($r['frequency']) . '</strong> recurring donation to Sharan Foundation! 💝</p>';
    $body .= '<div style="background:#fffaf0;border:2px solid #f4a261;padding:18px;margin:18px 0;border-radius:10px;text-align:center">';
    $body .= '<p style="margin:0;font-size:14px;color:#5b4a2c;letter-spacing:2px;text-transform:uppercase">Your Recurring Gift</p>';
    $body .= '<p style="margin:6px 0;font-size:28px;color:#2563eb;font-weight:800">' . $amount . ' / ' . ucfirst($r['frequency']) . '</p>';
    $body .= '<p style="margin:0;font-size:13px;color:#5b4a2c">Supporting: <strong>' . htmlspecialchars($r['purpose']) . '</strong></p>';
    $body .= '<p style="margin:6px 0 0;font-size:13px;color:#5b4a2c">Next charge: <strong>' . htmlspecialchars($r['next_charge_date']) . '</strong></p>';
    $body .= '</div>';
    $body .= '<p>You can <strong>manage, pause, or cancel</strong> your recurring donation anytime using your personal link:</p>';
    $body .= '<p style="text-align:center;background:#f9fafb;padding:10px;border-radius:6px;font-family:monospace;font-size:11px;word-break:break-all"><a href="' . htmlspecialchars($manage_url) . '">' . htmlspecialchars($manage_url) . '</a></p>';
    $body .= '<p style="margin-top:20px"><em>"Each one must give as he has decided in his heart, not reluctantly or under compulsion, for God loves a cheerful giver." — 2 Corinthians 9:7</em></p>';
    $body .= '<p>With deep gratitude,<br><strong>The Sharan Foundation Team</strong></p>';

    return send_mail($r['email'],
        '🎉 Your recurring donation is active — Sharan Foundation',
        email_template('💝 Recurring Donation Activated!', $body, '⚙️ Manage Your Donation', $manage_url));
}

/**
 * Sent N days BEFORE the next charge, as a heads-up to the donor.
 */
function notify_recurring_reminder($r, $days_until) {
    $sym = $r['currency'] === 'INR' ? '₹' : ($r['currency'] === 'GBP' ? '£' : '$');
    $amount = $sym . number_format($r['amount'], 0);
    $manage_url = BASE_URL . 'pages/manage-donation.php?token=' . $r['manage_token'];

    $body  = '<p>Dear ' . htmlspecialchars($r['donor_name']) . ',</p>';
    $body .= '<p>This is a friendly reminder that your recurring donation will be processed in <strong>' . (int)$days_until . ' day' . ($days_until == 1 ? '' : 's') . '</strong>.</p>';
    $body .= '<div style="background:#fef7e0;border-left:4px solid #d4a017;padding:15px;border-radius:6px;margin:15px 0">';
    $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size:14px">';
    $body .= '<tr><td style="padding:4px 0;width:130px;color:#5b4a2c">Amount:</td><td style="padding:4px 0"><strong>' . $amount . '</strong></td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#5b4a2c">Frequency:</td><td style="padding:4px 0">' . htmlspecialchars(ucfirst($r['frequency'])) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#5b4a2c">Charge Date:</td><td style="padding:4px 0"><strong>' . htmlspecialchars($r['next_charge_date']) . '</strong></td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#5b4a2c">Cause:</td><td style="padding:4px 0">' . htmlspecialchars($r['purpose']) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#5b4a2c">Method:</td><td style="padding:4px 0">' . htmlspecialchars(strtoupper(str_replace('_', ' ', $r['payment_method']))) . '</td></tr>';
    $body .= '</table>';
    $body .= '</div>';

    if (in_array($r['payment_method'], ['bank_transfer','upi','standing_order'])) {
        $body .= '<p><strong>Please ensure your payment is made on or before ' . htmlspecialchars($r['next_charge_date']) . '.</strong></p>';
        if ($r['currency'] === 'INR') {
            $body .= '<div style="background:#f9fafb;padding:12px;border-radius:6px;font-size:13px;line-height:1.7">';
            $body .= '<strong>🇮🇳 Payment details:</strong><br>UPI: <strong>sharanfoundation@upi</strong><br>Bank: SBI A/c 1234567890123, IFSC SBIN0001234';
            $body .= '</div>';
        } else {
            $body .= '<div style="background:#f9fafb;padding:12px;border-radius:6px;font-size:13px;line-height:1.7">';
            $body .= '<strong>🇬🇧 Payment details:</strong><br>Sharan Foundation UK • A/c 12345678 • Sort 12-34-56 • Barclays London';
            $body .= '</div>';
        }
    }
    $body .= '<p style="margin-top:18px">Want to make changes? You can update, pause, or cancel anytime:</p>';
    $body .= '<p>Total raised through your recurring gift so far: <strong>' . $sym . number_format($r['total_raised'], 0) . '</strong> across <strong>' . (int)$r['total_cycles'] . '</strong> successful cycles. Thank you! 🙏</p>';

    return send_mail($r['email'],
        '⏰ Upcoming donation reminder — ' . $amount . ' in ' . $days_until . ' days',
        email_template('⏰ Donation Reminder', $body, '⚙️ Manage Donation', $manage_url));
}

/**
 * Sent on the charge date for manual-payment recurring donations (bank/upi).
 */
function notify_recurring_due($r) {
    $sym = $r['currency'] === 'INR' ? '₹' : ($r['currency'] === 'GBP' ? '£' : '$');
    $amount = $sym . number_format($r['amount'], 0);
    $manage_url = BASE_URL . 'pages/manage-donation.php?token=' . $r['manage_token'];

    $body  = '<p>Dear ' . htmlspecialchars($r['donor_name']) . ',</p>';
    $body .= '<p>Your recurring donation of <strong>' . $amount . '</strong> is due <strong>today</strong>.</p>';
    $body .= '<p>Please make the payment using your usual method:</p>';
    if ($r['currency'] === 'INR') {
        $body .= '<div style="background:#fffaf0;border:1px solid #f4a261;padding:15px;border-radius:8px;font-size:13px;line-height:1.8">';
        $body .= '<strong>UPI ID:</strong> sharanfoundation@upi<br>';
        $body .= '<strong>Bank:</strong> State Bank of India<br>';
        $body .= '<strong>A/c No:</strong> 1234567890123<br>';
        $body .= '<strong>IFSC:</strong> SBIN0001234<br>';
        $body .= '<strong>Reference:</strong> REC-' . $r['id'] . '-' . date('Ym');
        $body .= '</div>';
    } else {
        $body .= '<div style="background:#fffaf0;border:1px solid #f4a261;padding:15px;border-radius:8px;font-size:13px;line-height:1.8">';
        $body .= '<strong>Bank:</strong> Barclays<br>';
        $body .= '<strong>A/c:</strong> 12345678<br>';
        $body .= '<strong>Sort:</strong> 12-34-56<br>';
        $body .= '<strong>Reference:</strong> REC-' . $r['id'] . '-' . date('Ym');
        $body .= '</div>';
    }
    $body .= '<p style="margin-top:18px"><em>Once we receive your payment, we will send you an official PDF receipt.</em></p>';
    $body .= '<p>Thank you for your continued generosity! 🙏</p>';

    return send_mail($r['email'],
        '💝 Today: Your recurring donation of ' . $amount,
        email_template('💝 Donation Due Today', $body, '⚙️ Manage Donation', $manage_url));
}

/**
 * Sent when a recurring charge has been successfully processed.
 */
function notify_recurring_charged($r, $donation_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch();
    if ($donation) {
        return send_donation_receipt($donation); // reuse the full PDF receipt flow
    }
    return [false, 'donation row not found'];
}

/**
 * Sent when payment failed (gateway error / card expired etc.).
 */
function notify_recurring_failed($r, $error_message) {
    $sym = $r['currency'] === 'INR' ? '₹' : ($r['currency'] === 'GBP' ? '£' : '$');
    $amount = $sym . number_format($r['amount'], 0);
    $manage_url = BASE_URL . 'pages/manage-donation.php?token=' . $r['manage_token'];

    $body  = '<p>Dear ' . htmlspecialchars($r['donor_name']) . ',</p>';
    $body .= '<p>We tried to process your recurring donation of <strong>' . $amount . '</strong> today, but unfortunately the payment did not go through.</p>';
    $body .= '<p style="background:#fdecea;border-left:4px solid #c0392b;padding:10px 15px;border-radius:6px;color:#c0392b;font-size:13px">' . htmlspecialchars($error_message) . '</p>';
    $body .= '<p>Common reasons: insufficient balance, expired card, or bank restrictions.</p>';
    $body .= '<p><strong>What to do:</strong> Please update your payment method or make a one-time payment. We\'ll automatically retry in 3 days.</p>';

    return send_mail($r['email'],
        '⚠️ Recurring donation payment failed',
        email_template('Payment Issue with Your Recurring Donation', $body, '⚙️ Update Now', $manage_url));
}

/**
 * Sent when donor pauses or cancels.
 */
function notify_recurring_cancelled($r) {
    $body  = '<p>Dear ' . htmlspecialchars($r['donor_name']) . ',</p>';
    $body .= '<p>We have ' . ($r['status'] === 'cancelled' ? 'cancelled' : 'paused') . ' your recurring donation as requested. 💚</p>';
    if ((float)$r['total_raised'] > 0) {
        $sym = $r['currency'] === 'INR' ? '₹' : ($r['currency'] === 'GBP' ? '£' : '$');
        $body .= '<p>Through your generous recurring gift you have contributed <strong>' . $sym . number_format($r['total_raised'], 0) . '</strong> across <strong>' . (int)$r['total_cycles'] . '</strong> cycles.</p>';
        $body .= '<p><strong>Thank you for the lives you have helped transform!</strong> 🙏</p>';
    }
    $body .= '<p>If you change your mind, you can always start a new recurring donation from <a href="' . BASE_URL . 'pages/donate.php">our donation page</a>.</p>';

    return send_mail($r['email'],
        '✓ Your recurring donation has been ' . $r['status'],
        email_template('Recurring Donation ' . ucfirst($r['status']), $body, 'Visit Our Site', BASE_URL));
}

/**
 * Email a payment-confirmed receipt to the donor — with a generated PDF attached.
 */
function send_donation_receipt($data) {
    $sym = $data['currency'] === 'INR' ? '₹' : ($data['currency'] === 'GBP' ? '£' : '$');
    $amount = $sym . number_format((float)$data['amount'], 2);
    $rcpt_no = $data['receipt_number'] ?: 'N/A';

    // Generate the PDF receipt (silently — non-fatal if it fails)
    $pdf_path = null;
    try {
        require_once __DIR__ . '/receipt_pdf.php';
        $pdf_path = generate_receipt_pdf($data);
    } catch (Throwable $e) { $pdf_path = null; }

    $body  = '<p>Dear ' . htmlspecialchars($data['donor_name']) . ',</p>';
    $body .= '<p>Thank you for your generous gift! 🙏 This email confirms we have received your donation.</p>';
    $body .= '<div style="background:#fffaf0;border:2px solid #f4a261;padding:20px;margin:20px 0;border-radius:10px;text-align:center">';
    $body .= '<p style="margin:0;font-size:14px;color:#5b4a2c;letter-spacing:2px;text-transform:uppercase">Official Donation Receipt</p>';
    $body .= '<p style="margin:6px 0;font-size:32px;font-weight:800;color:#2563eb">' . $amount . '</p>';
    $body .= '<p style="margin:0;font-size:13px;color:#5b4a2c">Receipt No: <strong>' . htmlspecialchars($rcpt_no) . '</strong></p>';
    $body .= '<p style="margin:6px 0 0;font-size:13px;color:#5b4a2c">Date: ' . htmlspecialchars($data['payment_date'] ?? date('Y-m-d')) . '</p>';
    $body .= '</div>';

    if ($pdf_path) {
        $body .= '<div style="background:#e8f5ef;padding:15px;border-radius:8px;text-align:center;margin:15px 0">';
        $body .= '<p style="margin:0;color:#1d4ed8;font-size:14px"><strong>📎 Official PDF Receipt Attached</strong></p>';
        $body .= '<p style="margin:5px 0 0;font-size:14px;color:#1d4ed8">Please find your official tax-exemption receipt attached to this email. Keep it safe for your tax records.</p>';
        $body .= '</div>';
    }

    $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:15px 0;font-size:14px">';
    $body .= '<tr><td style="padding:8px;background:#f9fafb;font-weight:600;width:160px">Donor</td><td style="padding:8px">' . htmlspecialchars($data['donor_name']) . '</td></tr>';
    if (!empty($data['pan_number'])) $body .= '<tr><td style="padding:8px;background:#f9fafb;font-weight:600">PAN</td><td style="padding:8px">' . htmlspecialchars($data['pan_number']) . '</td></tr>';
    $body .= '<tr><td style="padding:8px;background:#f9fafb;font-weight:600">Purpose</td><td style="padding:8px">' . htmlspecialchars($data['purpose'] ?? 'Where Most Needed') . '</td></tr>';
    $body .= '<tr><td style="padding:8px;background:#f9fafb;font-weight:600">Payment Method</td><td style="padding:8px">' . htmlspecialchars(strtoupper(str_replace('_', ' ', $data['payment_method']))) . '</td></tr>';
    if (!empty($data['transaction_id'])) $body .= '<tr><td style="padding:8px;background:#f9fafb;font-weight:600">Transaction ID</td><td style="padding:8px">' . htmlspecialchars($data['transaction_id']) . '</td></tr>';
    $body .= '</table>';

    if ($data['currency'] === 'INR') {
        $body .= '<p style="font-size:14px;color:#666;border-top:1px dashed #ddd;padding-top:12px">This donation is eligible for tax exemption under <strong>Section 80G</strong> of the Income Tax Act, 1961.</p>';
    } else {
        $body .= '<p style="font-size:14px;color:#666;border-top:1px dashed #ddd;padding-top:12px">Sharan Foundation UK is a registered charity. <strong>Gift Aid</strong> eligible donations welcome.</p>';
    }
    $body .= '<p style="margin-top:20px">May God bless you richly for your generosity!</p>';
    $body .= '<p><strong>The Sharan Foundation Team</strong></p>';

    return send_mail($data['email'],
        'Donation Receipt #' . $rcpt_no . ' — Sharan Foundation',
        email_template('📄 Your Donation Receipt', $body),
        null,
        $pdf_path ? [$pdf_path] : []);
}
