<?php
// Online payment via PayMongo Hosted Checkout (GCash, Maya, GrabPay, cards)
// + the single place where a booking becomes "Paid".
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/emails.php';

function paymongo_enabled(): bool {
    return defined('PAYMONGO_SECRET_KEY') && PAYMONGO_SECRET_KEY !== '';
}

function paymongo_request(string $method, string $path, ?array $body = null): array {
    $ch = curl_init('https://api.paymongo.com/v1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) throw new RuntimeException("PayMongo network error: $err");
    $json = json_decode($res, true) ?: [];
    if ($code >= 400) throw new RuntimeException("PayMongo HTTP $code: " . ($json['errors'][0]['detail'] ?? $res));
    return $json;
}

function paymongo_method_label(string $type): string {
    return ['gcash' => 'GCash', 'paymaya' => 'Maya', 'card' => 'Card', 'grab_pay' => 'GrabPay'][$type] ?? ($type !== '' ? ucfirst($type) : 'Online');
}

// Creates a hosted checkout page and returns its URL.
function paymongo_create_checkout(mysqli $conn, array $b): string {
    $nights = booking_nights($b);
    $res = paymongo_request('POST', '/checkout_sessions', ['data' => ['attributes' => [
        'billing'              => ['name' => $b['guest_name'], 'email' => $b['email']],
        'send_email_receipt'   => false,   // we send our own receipt
        'show_description'     => true,
        'show_line_items'      => true,
        'description'          => HOTEL_NAME . ' reservation ' . booking_ref($b),
        'reference_number'     => booking_ref($b),
        'line_items'           => [[
            'currency'    => 'PHP',
            'amount'      => (int)round((float)$b['price'] * 100),   // centavos
            'name'        => $b['room_name'] . ' (' . $b['check_in'] . ' to ' . $b['check_out'] . ')',
            'description' => 'Room rate per night',
            'quantity'    => $nights,
        ]],
        'payment_method_types' => ['gcash', 'paymaya', 'card', 'grab_pay'],
        'success_url'          => booking_link('payment_return.php', (int)$b['id']),
        'cancel_url'           => booking_link('payment.php', (int)$b['id']) . '&cancelled=1',
        'metadata'             => ['booking_id' => (string)$b['id']],
    ]]]);
    $sid = $res['data']['id'] ?? '';
    $url = $res['data']['attributes']['checkout_url'] ?? '';
    if ($sid === '' || $url === '') throw new RuntimeException('PayMongo returned no checkout URL');
    $s = $conn->prepare("UPDATE bookings SET payment_session_id = ? WHERE id = ?");
    $bid = (int)$b['id'];
    $s->bind_param("si", $sid, $bid);
    $s->execute();
    return $url;
}

// Re-fetches the session from PayMongo (never trusts a webhook body alone),
// checks it is really paid for the right amount, then settles the booking.
// Returns the booking id when paid, otherwise null.
function paymongo_settle_session(mysqli $conn, string $sessionId): ?int {
    $res = paymongo_request('GET', '/checkout_sessions/' . rawurlencode($sessionId));
    $a   = $res['data']['attributes'] ?? [];
    $bid = (int)($a['metadata']['booking_id'] ?? 0);
    if ($bid <= 0) return null;

    $paid = null;
    foreach (($a['payments'] ?? []) as $p) {
        if (($p['attributes']['status'] ?? '') === 'paid') { $paid = $p; break; }
    }
    if (!$paid) return null;

    $b = fetch_booking($conn, $bid);
    if (!$b) return null;
    $expected = (int)round(booking_total($b) * 100);
    if ((int)($paid['attributes']['amount'] ?? 0) < $expected) {
        error_log("PayMongo amount mismatch for booking $bid");
        return null;
    }
    $type = $a['payment_method_used'] ?? ($paid['attributes']['source']['type'] ?? '');
    mark_booking_paid($conn, $bid, paymongo_method_label((string)$type), (string)($paid['id'] ?? $sessionId));
    return $bid;
}

function paymongo_verify_signature(string $raw, string $header): bool {
    if (!defined('PAYMONGO_WEBHOOK_SECRET') || PAYMONGO_WEBHOOK_SECRET === '') return false;
    $parts = [];
    foreach (explode(',', $header) as $kv) {
        $p = explode('=', trim($kv), 2);
        if (count($p) === 2) $parts[$p[0]] = $p[1];
    }
    if (empty($parts['t'])) return false;
    $calc = hash_hmac('sha256', $parts['t'] . '.' . $raw, PAYMONGO_WEBHOOK_SECRET);
    foreach (['te', 'li'] as $k) {           // test / live signature
        if (!empty($parts[$k]) && hash_equals($calc, $parts[$k])) return true;
    }
    return false;
}

function issue_booking_invoice(mysqli $conn, int $id): void {
    $b = fetch_booking($conn, $id); if (!$b) return;
    $number = 'INV-' . booking_ref($b);
    $q = $conn->prepare('INSERT IGNORE INTO invoices(booking_id,invoice_number,amount,currency) VALUES(?,?,?,?)');
    $amount = booking_total($b); $currency = 'PHP'; $q->bind_param('isds', $id, $number, $amount, $currency); $q->execute();
}

// The ONE place a booking becomes Paid. Safe to call twice (webhook + return page):
// only the call that actually flips the row sends the emails.
function mark_booking_paid(mysqli $conn, int $id, ?string $method = null, ?string $reference = null): bool {
    $s = $conn->prepare(
        "UPDATE bookings
            SET payment_status = 'Paid',
                status = IF(status = 'Cancelled', status, 'Confirmed'),
                payment_method = COALESCE(?, payment_method),
                payment_reference = COALESCE(?, payment_reference),
                paid_at = NOW()
          WHERE id = ? AND payment_status <> 'Paid' AND status <> 'Cancelled'");
    $s->bind_param("ssi", $method, $reference, $id);
    $s->execute();
    if ($s->affected_rows !== 1) return false;
    try {
        issue_booking_invoice($conn, $id);
        $b = fetch_booking($conn, $id);
        $provider = 'system'; $amount = $b ? booking_total($b) : 0; $state = 'Paid';
        $tx = $conn->prepare('INSERT INTO payment_transactions(booking_id,provider,provider_reference,method,amount,status) VALUES(?,?,?,?,?,?)');
        $tx->bind_param('isssds', $id, $provider, $reference, $method, $amount, $state); $tx->execute();
        $actor = $_SESSION['admin_user'] ?? 'payment-system'; $action = 'payment_confirmed'; $entity = 'booking'; $details = json_encode(['method'=>$method,'reference'=>$reference]); $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $audit = $conn->prepare('INSERT INTO audit_log(actor,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)'); $audit->bind_param('sssiss', $actor, $action, $entity, $id, $details, $ip); $audit->execute();
    } catch (Throwable $e) { error_log('Payment ledger write failed: ' . $e->getMessage()); }
    try { send_payment_confirmed($conn, $id); } catch (Throwable $e) { error_log('Receipt email failed: ' . $e->getMessage()); }
    return true;
}
