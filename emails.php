<?php
// Email templates + senders. All guest-supplied text is escaped.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';

function email_shell(string $heading, string $inner, string $ctaLabel = '', string $ctaUrl = ''): string {
    $cta = $ctaUrl
        ? '<p style="margin:30px 0 0"><a href="' . h($ctaUrl) . '" style="display:inline-block;background:#d6b36a;color:#0c0c0c;text-decoration:none;font:600 12px Arial,sans-serif;letter-spacing:2px;padding:15px 30px;border-radius:999px">' . h($ctaLabel) . '</a></p>'
        : '';
    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#0c0c0c">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0c0c0c;padding:32px 12px"><tr><td align="center">'
      . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#151515;border:1px solid #2a2a2a;border-radius:18px">'
      . '<tr><td style="padding:34px 38px 8px;font:600 24px Georgia,serif;letter-spacing:4px;color:#d6b36a">LORENCE<div style="font:500 9px Arial,sans-serif;letter-spacing:4px;color:#999;margin-top:4px">GRAND HOTEL</div></td></tr>'
      . '<tr><td style="padding:18px 38px 38px;font:15px/1.7 Arial,sans-serif;color:#d8d8d8">'
      . '<h1 style="margin:0 0 14px;font:500 32px/1.15 Georgia,serif;color:#fff">' . h($heading) . '</h1>' . $inner . $cta . '</td></tr>'
      . '<tr><td style="padding:20px 38px;border-top:1px solid #2a2a2a;font:12px/1.7 Arial,sans-serif;color:#888">'
      . h(HOTEL_NAME) . ' · ' . h(HOTEL_ADDRESS) . '<br>' . h(HOTEL_PHONE) . ' · ' . h(HOTEL_EMAIL) . '</td></tr>'
      . '</table></td></tr></table></body></html>';
}

function email_rows(array $rows): string {
    $o = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;border-top:1px solid #2a2a2a">';
    foreach ($rows as $k => $v) {
        $o .= '<tr><td style="padding:11px 0;border-bottom:1px solid #2a2a2a;color:#999;font-size:13px">' . h($k)
            . '</td><td align="right" style="padding:11px 0;border-bottom:1px solid #2a2a2a;color:#fff;font-size:14px">' . h($v) . '</td></tr>';
    }
    return $o . '</table>';
}

function email_summary(array $b): array {
    return [
        'Reservation'  => booking_ref($b),
        'Room'         => $b['room_name'],
        'Check-in'     => $b['check_in'],
        'Check-out'    => $b['check_out'],
        'Nights'       => (string)booking_nights($b),
        'Guests'       => (string)$b['guests'],
        'Total'        => peso(booking_total($b)),
    ];
}

function notify_hotel(string $subject, string $html): void {
    send_mail(HOTEL_EMAIL, HOTEL_NAME, $subject, $html);
}

// 1) Right after the guest reserves
function send_booking_received(mysqli $conn, int $id): void {
    $b = fetch_booking($conn, $id); if (!$b) return;
    $inner = '<p>Hi ' . h($b['guest_name']) . ', thank you for choosing LORENCE. Your room is held while you complete payment.</p>'
           . email_rows(email_summary($b));
    $html = email_shell('Reservation received', $inner, 'COMPLETE PAYMENT', booking_link('payment.php', $id));
    send_mail($b['email'], $b['guest_name'], 'Reservation ' . booking_ref($b) . ' — complete your payment', $html);
}

// 2) Guest submitted a manual GCash / bank reference
function send_payment_submitted(mysqli $conn, int $id): void {
    $b = fetch_booking($conn, $id); if (!$b) return;
    $rows = email_summary($b) + ['Payment method' => $b['payment_method'], 'Reference' => $b['payment_reference']];
    $inner = '<p>We received your payment reference. Our team will verify it shortly and email your official receipt once confirmed.</p>' . email_rows($rows);
    send_mail($b['email'], $b['guest_name'], 'Payment reference received — ' . booking_ref($b),
        email_shell('Payment under review', $inner, 'VIEW STATUS', booking_link('confirmation.php', $id)));
    notify_hotel('Verify payment: ' . booking_ref($b),
        email_shell('Payment to verify', '<p>' . h($b['guest_name']) . ' submitted a reference.</p>' . email_rows($rows), 'OPEN ADMIN', site_url('admin/index.php')));
}

// 3) Payment confirmed (online or verified by admin) — includes receipt link
function send_payment_confirmed(mysqli $conn, int $id): void {
    $b = fetch_booking($conn, $id); if (!$b) return;
    $rows = email_summary($b) + [
        'Paid via'  => $b['payment_method'] ?: '—',
        'Reference' => $b['payment_reference'] ?: '—',
        'Status'    => 'PAID',
    ];
    $inner = '<p>Hi ' . h($b['guest_name']) . ', we have received your payment and your stay is confirmed. Your receipt is below, and you can print or save it any time.</p>' . email_rows($rows);
    send_mail($b['email'], $b['guest_name'], 'Payment received — invoice for ' . booking_ref($b),
        email_shell('Payment received', $inner, 'VIEW INVOICE', booking_link('invoice.php', $id)));
    notify_hotel('Paid: ' . booking_ref($b) . ' (' . peso(booking_total($b)) . ')',
        email_shell('Booking paid', email_rows($rows), 'OPEN ADMIN', site_url('admin/index.php')));
}
