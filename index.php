<?php
require "config.php";
$top = $conn->query("SELECT id,name,category,price,image FROM rooms WHERE is_active=1 ORDER BY price DESC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LORENCE Grand Hotel</title><link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="nav">
  <a class="logo" href="index.php">LORENCE <span>GRAND HOTEL</span></a>
  <nav class="site-nav"><a class="active" data-nav="home" href="index.php">Home</a><a data-nav="rooms" href="rooms.php">Rooms</a><a data-nav="amenities" href="#amenities">Amenities</a><a data-nav="experience" href="#experience">Experience</a><a data-nav="contact" href="#contact">Contact</a><a data-nav="track" href="tracking.php">Track booking</a></nav>
  <a class="btn btn-small" href="rooms.php">BOOK NOW</a>
</header>

<section class="hero">
  <div class="hero-overlay"></div>
  <div class="orb o1"></div><div class="orb o2"></div>
  <div class="hero-content">
    <p class="eyebrow">WELCOME TO LORENCE</p>
    <h1>Luxury Beyond<br><em>Expectation.</em></h1>
    <p class="hero-text">An extraordinary stay, designed around comfort, privacy, and timeless elegance.</p>
    <a class="btn" href="rooms.php">EXPLORE ROOMS</a>
  </div>
  <div class="hero-stage">
    <?php if ($top): ?>
    <a class="pane pane-room" style="--d:70" href="booking.php?room_id=<?=(int)$top['id']?>">
      <span class="pane-img" style="background-image:url('<?=htmlspecialchars($top['image'])?>')"></span>
      <span class="pane-body"><small><?=htmlspecialchars($top['category'])?></small><b><?=htmlspecialchars($top['name'])?></b><i>₱<?=number_format($top['price'],2)?> / night</i></span>
    </a>
    <?php endif; ?>
    <div class="pane pane-pool" style="--d:130"><b>Infinity Pool</b><span>A quiet rooftop retreat with uninterrupted city views.</span></div>
    <div class="pane pane-pay" style="--d:30"><b>Reserve online</b><span>Pay securely, receive your receipt by email.</span></div>
  </div>
</section>

<section class="intro intro-split">
  <p class="eyebrow">A SIGNATURE STAY</p>
  <h2>Where every detail<br>feels <em>exceptional.</em></h2>
  <p>From beautifully appointed suites to discreet five-star service, LORENCE is created for guests who expect more from every moment.</p>
  <div class="intro-links"><a href="#amenities">Explore amenities <span>↓</span></a><a href="#experience">Discover the experience <span>→</span></a></div>
</section>

<section id="amenities" class="amenities">
  <div class="section-head"><div><p class="eyebrow">WHAT WE OFFER</p><h2>Hotel Amenities</h2></div></div>
  <div class="amenity-cards"><article><b>01</b><h3>Rest & recharge</h3><p>Quiet rooms, premium linens, climate control, and considered spaces for unhurried mornings.</p></article><article><b>02</b><h3>Stay connected</h3><p>Fast Wi-Fi, smart entertainment, room service, and a front desk ready around the clock.</p></article><article><b>03</b><h3>Move & restore</h3><p>Swim, train, or reset with our rooftop pool, fitness center, and private spa services.</p></article><article><b>04</b><h3>Effortless arrival</h3><p>Secure parking, airport transfers, accessibility support, and personal assistance from check-in onward.</p></article></div>
</section>

<section id="experience" class="experience">
  <div><p class="eyebrow">THE LORENCE EXPERIENCE</p><h2>More than a room.<br><em>A private escape.</em></h2></div>
  <div class="features">
    <div><b>01</b><h3>Fine Dining</h3><p>Curated menus and intimate dining from morning to late evening.</p></div>
    <div><b>02</b><h3>Private Spa</h3><p>Restorative treatments in serene, beautifully designed spaces.</p></div>
    <div><b>03</b><h3>Infinity Pool</h3><p>A quiet rooftop retreat with uninterrupted city views.</p></div>
    <div><b>04</b><h3>Butler Service</h3><p>Personalized assistance, available whenever you need it.</p></div>
  </div>
</section>

<footer id="contact"><div><a class="logo" href="#">LORENCE <span>GRAND HOTEL</span></a><p>Luxury crafted for extraordinary moments.</p></div><div><p>Luxury Avenue, Manila, Philippines</p><p>+63 900 123 4567 · reservations@lorence.test</p></div></footer>
<script src="js/spatial.js" defer></script>
</body></html>
