<?php
/**
 * Shared helpers used by all payment success/webhook handlers.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/payments.php';

/**
 * Mark a donation as completed, generate receipt number, send PDF receipt.
 * Idempotent — calling twice does nothing on the 2nd call.
 */
function finalize_paid_donation(PDO $pdo, int $donation_id, string $gateway, ?string $payment_id, array $extra = []): array {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
    $stmt->execute([$donation_id]);
    $d = $stmt->fetch();
    if (!$d) return ['ok' => false, 'error' => 'donation not found'];

    // Idempotency: if already completed with same payment_id, skip
    if ($d['payment_status'] === 'completed' && !empty($d['gateway_payment_id']) && $d['gateway_payment_id'] === $payment_id) {
        return ['ok' => true, 'already' => true, 'donation' => $d];
    }

    // Generate receipt number if not already set
    $receipt_no = $d['receipt_number'];
    if (empty($receipt_no)) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE receipt_number IS NOT NULL")->fetchColumn();
        $receipt_no = sprintf('AF-%s-%04d', date('Y'), $count + 1);
    }

    $update = array_merge([
        'payment_status'      => 'completed',
        'gateway'             => $gateway,
        'gateway_payment_id'  => $payment_id,
        'transaction_id'      => $payment_id,
        'payment_date'        => date('Y-m-d'),
        'receipt_number'      => $receipt_no,
    ], $extra);

    $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($update)));
    $update['id'] = $donation_id;
    $pdo->prepare("UPDATE donations SET $set WHERE id=:id")->execute($update);

    // Send receipt with PDF
    $fresh = $pdo->query("SELECT * FROM donations WHERE id=$donation_id")->fetch();
    try { send_donation_receipt($fresh); } catch (Throwable $e) { /* silent */ }

    // If this was a recurring donation, create the recurring profile
    try {
        if ($fresh && in_array($fresh['donation_type'], ['monthly','yearly']) || !empty($_GET['recurring'])) {
            create_recurring_profile_if_missing($pdo, $fresh, $gateway, $extra);
        }
    } catch (Throwable $e) { /* silent */ }

    return ['ok' => true, 'donation' => $fresh];
}

/**
 * Create a recurring_donations profile from a one-off donation (if monthly/yearly was selected).
 */
function create_recurring_profile_if_missing(PDO $pdo, array $donation, string $gateway, array $extra = []): void {
    if (!in_array($donation['donation_type'], ['monthly','yearly'])) return;

    // Don't create duplicates: only if no recurring row already references this donation
    $exists = $pdo->prepare("SELECT id FROM recurring_donations WHERE created_donation_id=?");
    $exists->execute([$donation['id']]);
    if ($exists->fetch()) return;

    $freq = $donation['donation_type'] === 'yearly' ? 'yearly' : 'monthly';
    $mods = ['weekly'=>'+1 week','monthly'=>'+1 month','quarterly'=>'+3 months','yearly'=>'+1 year'];
    $next_date = date('Y-m-d', strtotime('today ' . ($mods[$freq] ?? '+1 month')));

    $row = [
        'donor_name'           => $donation['donor_name'],
        'email'                => $donation['email'],
        'phone'                => $donation['phone'],
        'country'              => $donation['country'],
        'pan_number'           => $donation['pan_number'],
        'amount'               => $donation['amount'],
        'currency'             => $donation['currency'],
        'frequency'            => $freq,
        'purpose'              => $donation['purpose'],
        'payment_method'       => $gateway,
        'gateway'              => $gateway,
        'gateway_customer_id'  => $extra['gateway_customer_id']    ?? null,
        'gateway_subscription_id' => $extra['gateway_subscription_id'] ?? null,
        'status'               => 'active',
        'start_date'           => date('Y-m-d'),
        'next_charge_date'     => $next_date,
        'last_charged_at'      => date('Y-m-d H:i:s'),
        'total_cycles'         => 1,
        'total_raised'         => $donation['amount'],
        'manage_token'         => bin2hex(random_bytes(16)),
        'created_donation_id'  => $donation['id'],
    ];
    $cols  = implode(',', array_keys($row));
    $place = ':' . implode(',:', array_keys($row));
    $pdo->prepare("INSERT INTO recurring_donations ($cols) VALUES ($place)")->execute($row);

    // Send activation email with manage link
    try { notify_recurring_started($row); } catch (Throwable $e) { /* silent */ }
}

/**
 * Mark a donation as failed and log the reason.
 */
function mark_donation_failed(PDO $pdo, int $donation_id, string $reason, ?string $raw = null): void {
    $pdo->prepare("UPDATE donations SET payment_status='failed', admin_notes=CONCAT(COALESCE(admin_notes,''),'\nFAILED: ',?), gateway_response_raw=COALESCE(?, gateway_response_raw) WHERE id=?")
        ->execute([$reason, $raw, $donation_id]);
}

/**
 * Log a webhook event for audit. Returns true if this is a NEW event (not duplicate).
 */
function log_webhook(PDO $pdo, string $gateway, string $event_type, ?string $event_id, string $payload, bool $sig_ok, string $result = '', ?int $donation_id = null): bool {
    // Duplicate check
    if ($event_id) {
        $dupe = $pdo->prepare("SELECT id FROM webhook_events WHERE event_id=?");
        $dupe->execute([$event_id]);
        if ($dupe->fetch()) return false;
    }
    $pdo->prepare("INSERT INTO webhook_events (gateway, event_type, event_id, payload, signature_ok, processed, result, donation_id) VALUES (?,?,?,?,?,1,?,?)")
        ->execute([$gateway, $event_type, $event_id, $payload, $sig_ok ? 1 : 0, $result, $donation_id]);
    return true;
}

/**
 * Redirect the donor to a friendly success/failure page.
 */
function redirect_donor(string $url): void {
    header('Location: ' . $url);
    exit;
}
