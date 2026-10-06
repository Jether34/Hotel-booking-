<?php
// Register this URL in the PayMongo dashboard (Developers > Webhooks),
// event: checkout_session.payment.paid. It must be publicly reachable (https).
require "config.php";
require "payments.php";

$raw = file_get_contents('php://input');
if (!paymongo_verify_signature($raw, $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '')) {
    http_response_code(400); exit('invalid signature');
}

$evt  = json_decode($raw, true) ?: [];
$type = $evt['data']['attributes']['type'] ?? '';
try {
    if ($type === 'checkout_session.payment.paid') {
        $sid = $evt['data']['attributes']['data']['id'] ?? '';
        if ($sid !== '') paymongo_settle_session($conn, $sid);
    }
    http_response_code(200); echo 'ok';
} catch (Throwable $e) {
    error_log('PayMongo webhook: ' . $e->getMessage());
    http_response_code(500); echo 'error';   // PayMongo will retry
}
