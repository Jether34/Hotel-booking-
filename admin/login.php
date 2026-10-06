<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../helpers.php';
if (!function_exists('h')) { function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['lorence_admin'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $u = trim($_POST['username'] ?? ''); $p = $_POST['password'] ?? '';
    $q = $conn->prepare('SELECT username,password_hash FROM admin_users WHERE username=? AND is_active=1');
    $q->bind_param('s', $u); $q->execute(); $a = $q->get_result()->fetch_assoc();
    if ($a && password_verify($p, $a['password_hash'])) { session_regenerate_id(true); $_SESSION['lorence_admin'] = true; $_SESSION['admin_user'] = $a['username']; header('Location: index.php'); exit; }
    $error = 'Invalid staff credentials.';
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Staff login | LORENCE</title><link rel="stylesheet" href="../css/style.css"></head><body><main class="auth-card"><p class="eyebrow">LORENCE STAFF</p><h1>Admin sign in</h1><?php if($error):?><div class="error"><?=h($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><label>USERNAME<input name="username" required autofocus></label><label>PASSWORD<input type="password" name="password" required></label><button class="btn full" type="submit">SIGN IN</button></form></main></body></html>
