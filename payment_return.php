<?php
// Where PayMongo sends the guest after paying. Confirms with PayMongo directly,
// so it works even before (or without) the webhook, e.g. on localhost.
require "config.php";
require "csrf.php";
require "payments.php";

$id    = (int)($_GET['id'] ?? 0);
$token = $_GET['t'] ?? '';
if (!booking_token_verify($id, $token)) { http_response_code(403); die("You don't have access to this reservation."); }

$b = fetch_booking($conn, $id);
if (!$b) die("Booking not found.");

if ($b['payment_status'] !== 'Paid' && paymongo_enabled() && !empty($b['payment_session_id'])) {
    try { paymongo_settle_session($conn, $b['payment_session_id']); } catch (Throwable $e) { error_log('Return check: ' . $e->getMessage()); }
    $b = fetch_booking($conn, $id);
}
if ($b['payment_status'] === 'Paid') {
    header("Location: " . "receipt.php?id=" . $id . "&t=" . urlencode($token)); exit;
}

$tries = (int)($_GET['n'] ?? 0);
$next  = "payment_return.php?id=$id&t=" . urlencode($token) . "&n=" . ($tries + 1);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<?php if ($tries < 6): ?><meta http-equiv="refresh" content="4;url=<?=h($next)?>"><?php endif; ?>
<title>Confirming payment | LORENCE</title><link rel="stylesheet" href="css/style.css"></head>
<body><main class="confirmation">
<p class="eyebrow"><?= $tries < 6 ? 'CONFIRMING YOUR PAYMENT' : 'STILL PROCESSING' ?></p>
<h1><?= $tries < 6 ? 'One moment…' : 'We’re still checking.' ?></h1>
<p><?= $tries < 6 ? 'We’re waiting for the payment network to confirm. This page refreshes on its own.' : 'If you completed the payment it will appear shortly, and we’ll email your receipt. You can also check the status below.' ?></p>
<a class="btn" href="confirmation.php?id=<?=$id?>&t=<?=h(urlencode($token))?>">VIEW RESERVATION</a>
</main><script src="js/spatial.js" defer></script></body></html>
