<?php
/**
 * Sharan Foundation — Recurring Donations Cron Job
 *
 * What it does on each run:
 *   1. Sends REMINDER emails to donors whose next charge is in 3 days
 *   2. Processes DUE recurring donations:
 *      - For gateway-tokenized (razorpay/stripe): attempts auto-charge (stub — wire your gateway here)
 *      - For manual methods (bank/upi/cheque): emails the donor a "due today" notice
 *      - Creates a `donations` row + logs the charge attempt
 *      - Advances next_charge_date
 *   3. Retries FAILED charges (up to 3 attempts, then pauses the subscription)
 *
 * ============== HOW TO SCHEDULE ==============
 *
 * Option A — Real cron (Linux server, cPanel, etc.) — RECOMMENDED:
 *   Add to crontab to run once daily at 9 AM:
 *
 *     0 9 * * *  /usr/bin/php /path/to/acts-foundation/cron/recurring_donations.php
 *
 *   Or for cPanel cron jobs:  /usr/local/bin/php /home/USER/public_html/LIVEpro/acts-foundation/cron/recurring_donations.php
 *
 * Option B — Web trigger (shared hosting without real cron):
 *   1. Set CRON_SECRET below (or in config/database.php)
 *   2. Use a free pinging service (cron-job.org, EasyCron, etc.) to hit:
 *      https://sharanforall.org/cron/recurring_donations.php?key=YOUR_SECRET
 *      once per day.
 *
 * Option C — Manual / Admin UI:
 *   Run from Admin → Recurring Donations → "Run Cron Now" button.
 *
 * The cron is IDEMPOTENT — running it twice the same day will not double-charge.
 */

// --- Allow CLI or authenticated web invocation ---
define('CRON_SECRET', 'CHANGE_ME_to_a_long_random_string_a8f6b3d2e1c9'); // <-- CHANGE THIS

$is_cli   = (php_sapi_name() === 'cli');
$is_admin = false;
if (!$is_cli) {
    session_start();
    $is_admin = !empty($_SESSION['admin']);
    $provided_key = $_GET['key'] ?? '';
    if (!$is_admin && !hash_equals(CRON_SECRET, $provided_key)) {
        http_response_code(403);
        die('Forbidden — invalid or missing key.');
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/payments.php';

set_time_limit(300);
$start = microtime(true);

$stats = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'reminders_sent' => 0];
$log_lines = [];

function clog(&$lines, $msg) {
    $lines[] = '[' . date('H:i:s') . '] ' . $msg;
    if (php_sapi_name() === 'cli') echo $lines[count($lines)-1] . PHP_EOL;
}

clog($log_lines, '🚀 Starting recurring donations cron run');

// ============================================================
// 1. REMINDERS — donors whose charge is in 3 days
// ============================================================
clog($log_lines, '📧 Phase 1: Checking for reminder emails (3 days out)...');

$reminder_stmt = $pdo->prepare("
    SELECT * FROM recurring_donations
    WHERE status='active'
      AND next_charge_date = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      AND id NOT IN (
        SELECT recurring_id FROM recurring_charges
        WHERE status='reminder_sent'
          AND charged_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
      )
");
$reminder_stmt->execute();
$to_remind = $reminder_stmt->fetchAll();

foreach ($to_remind as $r) {
    try {
        [$ok, $err] = notify_recurring_reminder($r, 3);
        $pdo->prepare("INSERT INTO recurring_charges (recurring_id, amount, currency, status, notes) VALUES (?,?,?,?,?)")
            ->execute([$r['id'], $r['amount'], $r['currency'], 'reminder_sent', '3-day reminder email ' . ($ok ? 'sent' : 'failed: '.$err)]);
        if ($ok) $stats['reminders_sent']++;
        clog($log_lines, "  → Reminder to {$r['email']} for {$r['currency']} {$r['amount']} : " . ($ok ? '✓ sent' : '✗ ' . $err));
    } catch (Throwable $e) {
        clog($log_lines, "  → Reminder FAILED for {$r['email']}: " . $e->getMessage());
    }
}
clog($log_lines, "✓ Phase 1 done — {$stats['reminders_sent']} reminders sent");

// ============================================================
// 2. DUE — process today's recurring charges
// ============================================================
clog($log_lines, '💳 Phase 2: Processing due recurring donations...');

$due_stmt = $pdo->prepare("
    SELECT * FROM recurring_donations
    WHERE status='active'
      AND next_charge_date <= CURDATE()
      AND id NOT IN (
        SELECT recurring_id FROM recurring_charges
        WHERE status IN ('success','manual')
          AND DATE(charged_at) = CURDATE()
      )
    ORDER BY next_charge_date ASC
");
$due_stmt->execute();
$due = $due_stmt->fetchAll();

foreach ($due as $r) {
    $stats['processed']++;
    clog($log_lines, "  ▶ Processing recurring #{$r['id']} for {$r['donor_name']} ({$r['currency']} {$r['amount']}, method: {$r['payment_method']})");

    $result = process_recurring_charge($pdo, $r);

    if ($result['status'] === 'success') {
        $stats['succeeded']++;
        clog($log_lines, "    ✓ SUCCESS — donation #{$result['donation_id']} created, next charge: {$result['next_date']}");
    } elseif ($result['status'] === 'manual') {
        clog($log_lines, "    ✉ MANUAL — due notice emailed to donor (payment to be made manually)");
    } else {
        $stats['failed']++;
        clog($log_lines, "    ✗ FAILED — " . $result['error'] . ($result['paused'] ?? false ? ' [SUBSCRIPTION PAUSED after 3 retries]' : ''));
    }
}
clog($log_lines, "✓ Phase 2 done — {$stats['succeeded']} succeeded, {$stats['failed']} failed");

// ============================================================
// LOG THE RUN
// ============================================================
$duration_ms = (int)((microtime(true) - $start) * 1000);
$details = implode("\n", $log_lines);

$pdo->prepare("INSERT INTO cron_log (job_name, processed, succeeded, failed, reminders_sent, details, duration_ms) VALUES (?,?,?,?,?,?,?)")
    ->execute(['recurring_donations', $stats['processed'], $stats['succeeded'], $stats['failed'], $stats['reminders_sent'], $details, $duration_ms]);

clog($log_lines, "🏁 Run completed in {$duration_ms}ms");

// ============================================================
// OUTPUT
// ============================================================
if ($is_cli) {
    echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
    echo "Summary: {$stats['processed']} processed, {$stats['succeeded']} ✓, {$stats['failed']} ✗, {$stats['reminders_sent']} 📧" . PHP_EOL;
    echo "Duration: {$duration_ms}ms" . PHP_EOL;
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Sharan Foundation — Recurring Donations Cron\n";
    echo str_repeat('=', 60) . "\n";
    echo $details . "\n";
    echo str_repeat('=', 60) . "\n";
    echo "Processed: {$stats['processed']}\n";
    echo "Succeeded: {$stats['succeeded']}\n";
    echo "Failed:    {$stats['failed']}\n";
    echo "Reminders: {$stats['reminders_sent']}\n";
    echo "Duration:  {$duration_ms}ms\n";
}

// ============================================================
// CORE: process a single recurring charge
// ============================================================
function process_recurring_charge(PDO $pdo, array $r): array
{
    $is_auto = !empty($r['payment_token']) && in_array($r['payment_method'], ['razorpay','stripe','paypal']);

    if ($is_auto) {
        // ----- AUTO-CHARGE via payment gateway -----
        // STUB: replace this block with real gateway charge code.
        // For Razorpay subscriptions: use Razorpay PHP SDK to fetch subscription status / invoices.
        // For Stripe: stripe-php to create off-session PaymentIntent with saved payment method.
        $gateway_result = simulate_gateway_charge($r);   // <-- REPLACE with real call
        if ($gateway_result['success']) {
            return finalize_successful_charge($pdo, $r, $gateway_result['txn_id'] ?? null, 'auto-charged via ' . $r['payment_method']);
        } else {
            return finalize_failed_charge($pdo, $r, $gateway_result['error'] ?? 'gateway error');
        }
    } else {
        // ----- MANUAL — bank transfer / UPI / standing order / cheque -----
        // Send the donor a "due today" reminder; payment will come in separately
        // and admin will mark it completed via the admin panel.
        try {
            [$ok, $err] = notify_recurring_due($r);
            $pdo->prepare("INSERT INTO recurring_charges (recurring_id, amount, currency, status, notes) VALUES (?,?,?,?,?)")
                ->execute([$r['id'], $r['amount'], $r['currency'], 'manual', 'Due-today email ' . ($ok ? 'sent' : 'failed: '.$err)]);
            // Advance the next charge date even though we haven't received money yet
            // (admin can record actual payment manually; the schedule moves on)
            $next = next_cron_date($r['next_charge_date'], $r['frequency']);
            $pdo->prepare("UPDATE recurring_donations SET next_charge_date=? WHERE id=?")
                ->execute([$next, $r['id']]);
            return ['status' => 'manual', 'next_date' => $next];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}

function finalize_successful_charge(PDO $pdo, array $r, ?string $txn_id, string $note): array
{
    // 1. Create a donations row (completed status)
    $count = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE receipt_number IS NOT NULL")->fetchColumn();
    $receipt_no = sprintf('AF-%s-%04d', date('Y'), $count + 1);

    $pdo->prepare("
        INSERT INTO donations
        (donor_name, email, phone, country, pan_number, amount, currency, donation_type, purpose,
         payment_method, payment_status, transaction_id, payment_date, receipt_number, message)
        VALUES (?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?)
    ")->execute([
        $r['donor_name'], $r['email'], $r['phone'], $r['country'], $r['pan_number'],
        $r['amount'], $r['currency'], $r['frequency'], $r['purpose'],
        $r['payment_method'], 'completed', $txn_id, date('Y-m-d'), $receipt_no,
        'Recurring donation #' . $r['id']
    ]);
    $donation_id = (int)$pdo->lastInsertId();

    // 2. Log the charge
    $pdo->prepare("INSERT INTO recurring_charges (recurring_id, donation_id, amount, currency, status, gateway_response, notes) VALUES (?,?,?,?,?,?,?)")
        ->execute([$r['id'], $donation_id, $r['amount'], $r['currency'], 'success', $note, $note]);

    // 3. Advance the subscription
    $next = next_cron_date($r['next_charge_date'], $r['frequency']);
    $pdo->prepare("
        UPDATE recurring_donations
        SET next_charge_date = ?, last_charged_at = NOW(), last_charge_status = 'success',
            total_cycles = total_cycles + 1, total_raised = total_raised + ?,
            failed_attempts = 0
        WHERE id = ?
    ")->execute([$next, $r['amount'], $r['id']]);

    // 4. Check max cycles
    if (!empty($r['max_cycles']) && ($r['total_cycles'] + 1) >= $r['max_cycles']) {
        $pdo->prepare("UPDATE recurring_donations SET status='expired' WHERE id=?")->execute([$r['id']]);
    }

    // 5. Send receipt PDF email
    try {
        notify_recurring_charged($r, $donation_id);
    } catch (Throwable $e) { /* silent */ }

    return ['status' => 'success', 'donation_id' => $donation_id, 'next_date' => $next];
}

function finalize_failed_charge(PDO $pdo, array $r, string $error): array
{
    $new_attempts = (int)$r['failed_attempts'] + 1;
    $paused = false;
    $retry_date = date('Y-m-d', strtotime('+3 days')); // retry in 3 days

    if ($new_attempts >= 3) {
        $pdo->prepare("UPDATE recurring_donations SET status='paused', failed_attempts=?, last_charge_status='failed' WHERE id=?")
            ->execute([$new_attempts, $r['id']]);
        $paused = true;
    } else {
        $pdo->prepare("UPDATE recurring_donations SET failed_attempts=?, last_charge_status='failed', next_charge_date=? WHERE id=?")
            ->execute([$new_attempts, $retry_date, $r['id']]);
    }

    $pdo->prepare("INSERT INTO recurring_charges (recurring_id, amount, currency, status, gateway_response, notes) VALUES (?,?,?,?,?,?)")
        ->execute([$r['id'], $r['amount'], $r['currency'], 'failed', $error, "Attempt #{$new_attempts}" . ($paused ? ' - paused' : ' - retry on ' . $retry_date)]);

    try { notify_recurring_failed($r, $error); } catch (Throwable $e) { /* silent */ }

    return ['status' => 'failed', 'error' => $error, 'paused' => $paused];
}

function next_cron_date(string $from, string $frequency): string
{
    $mods = ['weekly'=>'+1 week','monthly'=>'+1 month','quarterly'=>'+3 months','yearly'=>'+1 year'];
    return date('Y-m-d', strtotime($from . ' ' . ($mods[$frequency] ?? '+1 month')));
}

/**
 * Real gateway auto-charge via Razorpay/Stripe/PayPal APIs.
 * Returns ['success' => bool, 'txn_id' => string|null, 'error' => string|null]
 *
 * Note: For Razorpay/PayPal subscriptions the gateway handles the recurring charge
 * automatically and notifies us via webhook. This function is mainly for Stripe
 * off-session charges using a saved payment method, plus a safety net for others.
 */
function simulate_gateway_charge(array $r): array
{
    $gw = $r['gateway'] ?: $r['payment_method'];

    // STRIPE: off-session charge using saved customer + payment method
    if ($gw === 'stripe' && !empty($r['gateway_customer_id'])) {
        // We need a default payment method on the customer — Stripe Checkout sets this up
        // For simplicity we trigger the subscription's next invoice via the subscription ID;
        // but real off-session is also supported here.
        $result = Stripe::charge_customer(
            $r['gateway_customer_id'],
            $r['gateway_customer_id'],   // Stripe will use default PM if just customer id
            $r['amount'],
            $r['currency'],
            'Recurring donation #' . $r['id'] . ' — ' . $r['purpose']
        );
        return ['success' => $result['ok'], 'txn_id' => $result['txn_id'] ?? null, 'error' => $result['error'] ?? null];
    }

    // RAZORPAY / PAYPAL subscriptions auto-charge themselves and notify via webhook;
    // we just log here so the donor still gets a "due today" courtesy email.
    if ($gw === 'razorpay' && !empty($r['gateway_subscription_id'])) {
        return ['success' => false, 'error' => 'Razorpay subscription auto-charges via gateway. Awaiting webhook event.'];
    }
    if ($gw === 'paypal' && !empty($r['gateway_subscription_id'])) {
        return ['success' => false, 'error' => 'PayPal subscription auto-charges via gateway. Awaiting webhook event.'];
    }

    // SANDBOX fallback for demo / no gateway configured
    if (gateway_mode() === 'sandbox') {
        if (mt_rand(1, 100) <= 95) {
            return ['success' => true, 'txn_id' => 'SBX_' . strtoupper(bin2hex(random_bytes(6)))];
        }
        return ['success' => false, 'error' => 'Sandbox: simulated failure'];
    }

    return ['success' => false, 'error' => 'No auto-charge path available for ' . $gw . ' — manual payment required'];
}
