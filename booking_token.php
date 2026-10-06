<?php
// Generates/verifies a per-booking access token so guests can only view
// their own booking's payment/confirmation page, not any ID by guessing.
// Requires BOOKING_TOKEN_SECRET to be defined in config.php, e.g.:
//   define('BOOKING_TOKEN_SECRET', bin2hex(random_bytes(32))); // generate once, keep fixed
if (!defined('BOOKING_TOKEN_SECRET')) {
    // Fail loudly rather than silently running with a guessable/absent secret.
    die('Server misconfiguration: BOOKING_TOKEN_SECRET is not set in config.php.');
}

function booking_token(int $bookingId): string {
    return hash_hmac('sha256', (string)$bookingId, BOOKING_TOKEN_SECRET);
}

function booking_token_verify(int $bookingId, ?string $token): bool {
    if (!$token) return false;
    return hash_equals(booking_token($bookingId), $token);
}
