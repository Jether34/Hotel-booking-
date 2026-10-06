<?php
date_default_timezone_set('Asia/Manila');
// Throw exceptions on DB errors instead of silently returning false
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db   = getenv('DB_NAME') ?: 'lorence_hotel';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Log the real error for developers, but never show connection
    // details (host/user/db) to site visitors.
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("We're unable to connect to the database right now. Please try again shortly.");
}

// Used by booking_token.php to sign per-booking access tokens so a guest
// can only view their own booking's payment/confirmation page, not any
// ID by guessing. Generated once -- keep this fixed. If you ever change
// it, every link already sent to a guest (payment/confirmation URLs)
// will stop working, since the token depends on this exact value.
define('BOOKING_TOKEN_SECRET', getenv('BOOKING_TOKEN_SECRET') ?: 'f1a76f0c3fb88d4e3ef7bd0c4f1a5b94e9c6e2a1d8b7c3f0a6e5d4c2b1a98765');
define('ADMIN_SESSION_KEY', 'lorence_admin');

// ---------------------------------------------------------------
// Site + hotel details (used in emails, receipts and payment links)
// ---------------------------------------------------------------
// SITE_URL must be the public address of this site, no trailing slash.
// Examples: 'http://localhost/lorence'  or  'https://www.yourhotel.com'
define('SITE_URL',      getenv('SITE_URL') ?: 'http://localhost/gian');
define('HOTEL_NAME',    'LORENCE Grand Hotel');
define('HOTEL_ADDRESS', 'Luxury Avenue, Manila, Philippines');
define('HOTEL_PHONE',   '+63 900 123 4567');
define('HOTEL_EMAIL',   'reservations@lorence.test'); // also receives "new payment" alerts

// ---------------------------------------------------------------
// Email
//   MAIL_DRIVER 'log'  -> nothing is sent; each email is saved as an .html
//                         file in /storage/mail (perfect for local testing)
//   MAIL_DRIVER 'smtp' -> real delivery through your SMTP account
//   MAIL_DRIVER 'mail' -> PHP's built-in mail() (needs a configured server)
// Gmail example: host smtp.gmail.com, port 587, secure 'tls',
//   user = your Gmail address, pass = a Google "App password".
// ---------------------------------------------------------------
define('MAIL_DRIVER',    getenv('MAIL_DRIVER') ?: 'log');
define('MAIL_HOST',      getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_SECURE',    getenv('MAIL_SECURE') ?: 'tls');
define('MAIL_USER',      getenv('MAIL_USER') ?: '');
define('MAIL_PASS',      getenv('MAIL_PASS') ?: '');
define('MAIL_FROM',      getenv('MAIL_FROM') ?: 'reservations@lorence.test');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'LORENCE Grand Hotel');

// ---------------------------------------------------------------
// Online payment (PayMongo: GCash, Maya, GrabPay, cards)
// Leave PAYMONGO_SECRET_KEY empty to keep the manual GCash/bank flow only.
// Use sk_test_... while testing, sk_live_... when you go live.
// ---------------------------------------------------------------
define('PAYMONGO_SECRET_KEY',     getenv('PAYMONGO_SECRET_KEY') ?: '');
define('PAYMONGO_WEBHOOK_SECRET', getenv('PAYMONGO_WEBHOOK_SECRET') ?: '');
?>
