<?php
// Shared helpers: money, links, booking lookups.
require_once __DIR__ . '/booking_token.php';

function peso(float $n): string { return '₱' . number_format($n, 2); }

function site_url(string $path = ''): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

// Signed link a guest can open without logging in, e.g. booking_link('receipt.php', 12)
function booking_link(string $page, int $id): string {
    return site_url($page . '?id=' . $id . '&t=' . booking_token($id));
}

function booking_nights(array $b): int {
    return max(1, (int)round((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400));
}

function booking_total(array $b): float {
    if (isset($b['total_amount']) && $b['total_amount'] !== null) return (float)$b['total_amount'];
    return booking_nights($b) * (float)($b['price_per_night'] ?? $b['price']);
}

function booking_ref(array $b): string {
    return (string)($b['booking_reference'] ?? ('LRC-' . str_pad((string)$b['id'], 6, '0', STR_PAD_LEFT)));
}

function fetch_booking(mysqli $conn, int $id): ?array {
    $s = $conn->prepare("SELECT b.*, r.name AS room_name, r.category AS room_category, b.price_per_night AS price
                         FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    return $s->get_result()->fetch_assoc() ?: null;
}

function room_images(mysqli $conn, int $roomId): array {
    $s = $conn->prepare('SELECT path FROM room_images WHERE room_id = ? ORDER BY sort_order, id');
    $s->bind_param('i', $roomId); $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
