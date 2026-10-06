<?php
require "config.php";
require "csrf.php";
require "payments.php";   // also loads helpers + emails

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$token = $_GET['t'] ?? $_POST['t'] ?? '';
if (!booking_token_verify($id, $token)) {
    http_response_code(403);
    die("You don't have access to this reservation.");
}
$b = fetch_booking($conn, $id);
if (!$b) die("Booking not found.");

if ($b['payment_status'] !== 'Paid') {
    header("Location: confirmation.php?id=" . $id . "&t=" . urlencode($token)); exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (($_SESSION['receipt_resent_' . $id] ?? 0) > time() - 60) {
        $msg = 'Please wait a minute before requesting another email.';
    } else {
        $_SESSION['receipt_resent_' . $id] = time();
        send_payment_confirmed($conn, $id);   // re-sends to the guest (and the hotel copy)
        $msg = 'Receipt sent to ' . $b['email'] . '.';
    }
}

$nights = booking_nights($b);
$rate   = (float)$b['price'];
$total  = booking_total($b);
$paidAt = $b['paid_at'] ?: $b['created_at'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Receipt <?=h(booking_ref($b))?> | LORENCE</title><link rel="stylesheet" href="css/style.css"></head>
<body>
<header class="nav dark no-print"><a class="logo" href="index.php">LORENCE <span>GRAND HOTEL</span></a><nav><a href="index.php">Home</a><a href="rooms.php">Rooms</a></nav></header>
<main class="receipt-page">
  <div class="receipt-actions no-print">
    <a class="btn btn-ghost" href="find.php">TRACK A BOOKING</a>
    <button class="btn" type="button" onclick="window.print()">DOWNLOAD / SAVE PDF</button>
    <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="t" value="<?=h($token)?>"><button class="btn btn-ghost" type="submit">EMAIL ME A COPY</button></form>
  </div>
  <?php if ($msg): ?><div class="notice no-print"><?=h($msg)?></div><?php endif; ?>

  <article class="receipt-sheet">
    <div class="stamp">PAID</div>
    <div class="r-head">
      <div><div class="r-brand">LORENCE</div><div class="r-sub">GRAND HOTEL</div></div>
      <div class="r-meta"><b>OFFICIAL RECEIPT</b><br>Receipt no. <?=h(booking_ref($b))?><br>Date paid: <?=h(date('F j, Y g:i A', strtotime($paidAt)))?></div>
    </div>
    <div class="r-grid">
      <div><small>BILLED TO</small><?=h($b['guest_name'])?><br><?=h($b['email'])?><br><?=h($b['phone'])?></div>
      <div><small>FROM</small><?=h(HOTEL_NAME)?><br><?=h(HOTEL_ADDRESS)?><br><?=h(HOTEL_PHONE)?></div>
      <div><small>STAY</small><?=h($b['check_in'])?> → <?=h($b['check_out'])?><br><?=$nights?> night<?=$nights>1?'s':''?> · <?=h($b['guests'])?> guest<?=$b['guests']>1?'s':''?></div>
      <div><small>PAYMENT</small><?=h($b['payment_method'] ?? '—')?><br>Ref: <?=h($b['payment_reference'] ?? '—')?></div>
    </div>
    <table class="r-table">
      <tr><th>Description</th><th class="num">Nights</th><th class="num">Rate</th><th class="num">Amount</th></tr>
      <tr><td><?=h($b['room_name'])?> <span class="muted">(<?=h($b['room_category'])?>)</span></td><td class="num"><?=$nights?></td><td class="num"><?=peso($rate)?></td><td class="num"><?=peso($total)?></td></tr>
    </table>
    <div class="r-total"><span>Total paid</span><span><?=peso($total)?></span></div>
    <p class="r-foot">Thank you for staying with LORENCE. Please present this receipt (printed or on your phone) at check-in. Questions? <?=h(HOTEL_EMAIL)?></p>
  </article>
</main>
<script src="js/spatial.js" defer></script>
</body></html>
