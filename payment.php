<?php
require "config.php";
require "csrf.php";
require "payments.php";

$id    = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$token = $_GET['t'] ?? $_POST['t'] ?? '';

if (!booking_token_verify($id, $token)) {
    http_response_code(403);
    die("You don't have access to this reservation.");
}

$b = fetch_booking($conn, $id);
if (!$b) die("Booking not found.");
if ($b['status'] === 'Cancelled') die("This reservation was cancelled.");
if ($b['payment_status'] === 'Unpaid' && !empty($b['expires_at']) && strtotime($b['expires_at']) < time()) {
    $q = $conn->prepare("UPDATE bookings SET status='Cancelled' WHERE id=? AND payment_status='Unpaid' AND status<>'Cancelled'"); $q->bind_param('i', $id); $q->execute();
    die("This payment hold has expired. Please start a new reservation.");
}

if ($b['payment_status'] !== 'Unpaid') {
    $page = $b['payment_status'] === 'Paid' ? 'receipt.php' : 'confirmation.php';
    header("Location: $page?id=" . $id . "&t=" . urlencode($token)); exit;
}

$nights = booking_nights($b);
$total  = booking_total($b);
$online = paymongo_enabled();

$error  = '';
$notice = !empty($_GET['cancelled']) ? 'Payment was cancelled and you were not charged. You can try again below.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = "Your session expired, please try again.";
    } elseif (($_POST['action'] ?? '') === 'online' && $online) {
        try {
            header("Location: " . paymongo_create_checkout($conn, $b)); exit;
        } catch (Throwable $e) {
            error_log('Checkout create failed: ' . $e->getMessage());
            $error = "We couldn't start the online payment. Please try again, or pay manually below.";
        }
    } else {
        $method    = $_POST['payment_method'] ?? '';
        $reference = trim($_POST['reference'] ?? '');
        if (!in_array($method, ['GCash', 'Bank Transfer'], true) || $reference === '') {
            $error = "Please choose a payment method and enter your reference number.";
        } else {
            $upd = $conn->prepare("UPDATE bookings SET payment_method=?, payment_reference=?, payment_status='Pending Verification' WHERE id=? AND payment_status='Unpaid'");
            $upd->bind_param("ssi", $method, $reference, $id);
            $upd->execute();
            try { send_payment_submitted($conn, $id); } catch (Throwable $e) { error_log($e->getMessage()); }
            header("Location: confirmation.php?id=" . $id . "&t=" . urlencode($token)); exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Payment | LORENCE</title><link rel="stylesheet" href="css/style.css"></head>
<body>
<header class="nav dark"><a class="logo" href="index.php">LORENCE <span>GRAND HOTEL</span></a><nav><a href="index.php">Home</a><a href="rooms.php">Rooms</a></nav></header>
<main class="payment-page">
  <div class="payment-summary">
    <p class="eyebrow">RESERVATION #<?=str_pad($b['id'],6,'0',STR_PAD_LEFT)?></p>
    <h1>Complete Payment</h1>
    <p><?=h($b['room_name'])?> · <?=h($b['check_in'])?> → <?=h($b['check_out'])?> · <?=(int)$nights?> night<?=$nights>1?'s':''?></p>
    <h3>₱<?=number_format($total,2)?> total</h3>
  </div>

  <?php if ($notice): ?><div class="notice"><?=h($notice)?></div><?php endif; ?>
  <?php if ($error): ?><div class="error"><?=h($error)?></div><?php endif; ?>

  <?php if ($online): ?>
  <form class="pay-online" method="post">
    <?=csrf_field()?>
    <input type="hidden" name="id" value="<?=$id?>">
    <input type="hidden" name="t" value="<?=h($token)?>">
    <input type="hidden" name="action" value="online">
    <p class="eyebrow">PAY ONLINE</p>
    <h3>Pay securely in a minute</h3>
    <p>You’ll be taken to a secure payment page. Your reservation is confirmed instantly and your receipt is emailed to you.</p>
    <div class="pay-chips"><span>GCash</span><span>Maya</span><span>GrabPay</span><span>Card</span></div>
    <button class="btn-reserve full" type="submit">PAY ₱<?=number_format($total,2)?> NOW <span>→</span></button>
  </form>
  <?php endif; ?>

  <details class="manual-pay" <?=$online?'':'open'?>>
    <summary><?=$online?'Prefer to pay manually? GCash or bank transfer':'Pay by GCash or bank transfer'?></summary>
    <form class="payment-form" method="post">
      <?=csrf_field()?>
      <input type="hidden" name="id" value="<?=$id?>">
      <input type="hidden" name="t" value="<?=h($token)?>">
      <input type="hidden" name="action" value="manual">

      <label class="payment-option gcash">
        <input type="radio" name="payment_method" value="GCash" required>
        <div class="method-icon">GC</div>
        <div>
          <strong>GCash</strong>
          <p>Send ₱<?=number_format($total,2)?> to <b>0917-000-0000</b> (LORENCE Grand Hotel). Save the reference number from your GCash receipt.</p>
          <a class="payment-action" href="https://www.gcash.com/" target="_blank" rel="noopener" onclick="event.stopPropagation()">Open GCash <span>↗</span></a>
        </div>
      </label>

      <label class="payment-option bank">
        <input type="radio" name="payment_method" value="Bank Transfer" required>
        <div class="method-icon">₱</div>
        <div>
          <strong>Bank Transfer</strong>
          <p>Transfer ₱<?=number_format($total,2)?> to BDO Account Name: <b>LORENCE Grand Hotel Corp</b>, Account #: <b>0012-3456-7890</b>. Save the transaction reference.</p>
          <a class="payment-action" href="https://online.bdo.com.ph/" target="_blank" rel="noopener" onclick="event.stopPropagation()">Go to BDO Online Banking <span>↗</span></a>
        </div>
      </label>

      <label>REFERENCE / TRANSACTION NUMBER<input name="reference" placeholder="e.g. GC-8823941021" required></label>
      <button class="btn-reserve full" type="submit">I'VE PAID — SUBMIT REFERENCE <span>→</span></button>
      <p class="payment-note">Your reservation stays <b>Pending</b> until our team verifies the payment. We’ll email your receipt as soon as it’s confirmed.</p>
    </form>
  </details>
</main>
<script src="js/spatial.js" defer></script>
</body></html>
