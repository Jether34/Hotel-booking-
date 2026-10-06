<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['lorence_admin'])) { header('Location: login.php'); exit; }

$message = ''; $error = '';
$editId = (int)($_GET['edit'] ?? $_POST['room_id'] ?? 0);
$editing = null;
if ($editId) {
    $q = $conn->prepare('SELECT * FROM rooms WHERE id=?'); $q->bind_param('i', $editId); $q->execute();
    $editing = $q->get_result()->fetch_assoc();
    if (!$editing) { $editId = 0; }
}

function save_room_uploads(mysqli $conn, int $roomId, array $files): array {
    $saved = []; $dir = __DIR__ . '/../uploads/rooms';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif'];
    $count = min(count($files['name'] ?? []), 10);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i=0; $i<$count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ($files['error'][$i] !== UPLOAD_ERR_OK || (int)$files['size'][$i] > 5 * 1024 * 1024) continue;
        $mime = $finfo->file($files['tmp_name'][$i]);
        if (!isset($allowed[$mime])) continue;
        $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        $path = 'uploads/rooms/' . $filename;
        if (move_uploaded_file($files['tmp_name'][$i], $dir . '/' . $filename)) {
            $sort = $i; $q = $conn->prepare('INSERT INTO room_images(room_id,path,sort_order) VALUES(?,?,?)');
            $q->bind_param('isi', $roomId, $path, $sort); $q->execute(); $saved[] = $path;
        }
    }
    return $saved;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)$_POST['room_id']; $q = $conn->prepare("UPDATE rooms SET is_active=0 WHERE id=?"); $q->bind_param('i',$id); $q->execute(); $message='Room removed from the public room list.';
    } elseif ($action === 'save') {
        $name=trim($_POST['name']??''); $category=trim($_POST['category']??''); $description=trim($_POST['description']??''); $price=(float)($_POST['price']??0); $max=(int)($_POST['max_guests']??1); $active=isset($_POST['is_active'])?1:0;
        if ($name==='' || $category==='' || $description==='' || $price<=0 || $max<1 || $max>50) $error='Complete the room details with a valid rate and capacity.';
        else {
            if ($editId) { $q=$conn->prepare('UPDATE rooms SET name=?,category=?,description=?,price=?,max_guests=?,is_active=? WHERE id=?'); $q->bind_param('sssdiii',$name,$category,$description,$price,$max,$active,$editId); }
            else { $placeholder=''; $q=$conn->prepare('INSERT INTO rooms(name,category,description,price,image,max_guests,is_active) VALUES(?,?,?,?,?,?,?)'); $q->bind_param('sssdsii',$name,$category,$description,$price,$placeholder,$max,$active); }
            $q->execute(); $roomId=$editId ?: $conn->insert_id; $new=save_room_uploads($conn,$roomId,$_FILES['images']??[]);
            if (!$editId && !$new) { $conn->query("DELETE FROM rooms WHERE id=".(int)$roomId); $error='Upload at least one JPG, PNG, WEBP, or GIF image (maximum 5 MB each).'; }
            else { if ($new) { $primary=$new[0]; $q=$conn->prepare('UPDATE rooms SET image=? WHERE id=?'); $q->bind_param('si',$primary,$roomId); $q->execute(); } $message=$editId?'Room updated.':'Room created and published.'; $editId=0; $editing=null; }
        }
    }
}
$rooms=$conn->query('SELECT * FROM rooms ORDER BY is_active DESC, name');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Manage rooms | LORENCE</title><link rel="stylesheet" href="../css/style.css"></head><body><header class="nav dark"><a class="logo" href="../index.php">LORENCE <span>STAFF CONSOLE</span></a><nav><a href="index.php">Reservations</a><a class="active" href="rooms.php">Rooms</a><a href="logout.php">Sign out</a></nav></header><main class="admin-page"><div class="page-title"><p class="eyebrow">INVENTORY</p><h1><?= $editing ? 'Edit room' : 'Add a room' ?></h1><p>Manage room details, capacity, visibility, and up to 10 gallery images.</p></div><?php if($message):?><div class="notice"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="error"><?=h($error)?></div><?php endif;?><form class="room-admin-form" method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="room_id" value="<?=$editId?>"><div class="form-row"><label>ROOM NAME<input name="name" required value="<?=h($editing['name']??'')?>"></label><label>CATEGORY<input name="category" required value="<?=h($editing['category']??'')?>"></label></div><label>DESCRIPTION<textarea name="description" rows="4" required><?=h($editing['description']??'')?></textarea></label><div class="form-row"><label>NIGHTLY RATE<input type="number" name="price" min="1" step="0.01" required value="<?=h($editing['price']??'')?>"></label><label>MAXIMUM GUESTS<input type="number" name="max_guests" min="1" max="50" required value="<?=h($editing['max_guests']??'4')?>"></label></div><label class="upload-drop">ROOM IMAGES <small>Choose up to 10 images, 5 MB each. JPG, PNG, WEBP, or GIF.</small><input type="file" name="images[]" accept=\"image/jpeg,image/png,image/webp,image/gif\" multiple></label><label class="check-label"><input type="checkbox" name="is_active" <?=$editing && !$editing['is_active']?'':'checked'?>> PUBLISH THIS ROOM</label><button class="btn" type="submit"><?= $editing ? 'SAVE ROOM' : 'ADD ROOM' ?></button><?php if($editing):?><a class="btn btn-ghost" href="rooms.php">CANCEL</a><?php endif;?></form><section class="admin-room-grid"><?php while($room=$rooms->fetch_assoc()):?><article class="admin-room-card"><div class="admin-room-thumb" style="background-image:url('../<?=h($room['image'])?>')"></div><div><p class="eyebrow"><?=h($room['category'])?></p><h3><?=h($room['name'])?></h3><p><?=peso((float)$room['price'])?> · up to <?=h($room['max_guests'])?> guests</p><span class="status"><?= $room['is_active']?'PUBLISHED':'HIDDEN' ?></span><div class="admin-room-actions"><a class="btn btn-small" href="?edit=<?=$room['id']?>">EDIT</a><?php if($room['is_active']):?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="room_id" value="<?=$room['id']?>"><button class="btn btn-small btn-danger" type="submit">DELETE</button></form><?php endif;?></div></div></article><?php endwhile;?></section></main></body></html>
