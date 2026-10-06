-- LORENCE Grand Hotel -- full setup.
-- Rebuilds the database from scratch. WARNING: this drops the existing
-- lorence_hotel database, so all previous bookings are lost.
--
-- Import once with:  mysql -u root < sql/lorence_setup.sql

DROP DATABASE IF EXISTS `lorence_hotel`;
CREATE DATABASE `lorence_hotel`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `lorence_hotel`;

-- ---------------------------------------------------------------
-- rooms: the bookable inventory shown by index.php / rooms.php
-- ---------------------------------------------------------------
CREATE TABLE `rooms` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120)  NOT NULL,
  `category`    VARCHAR(60)   NOT NULL,
  `description` TEXT          NOT NULL,
  -- Nightly rate in pesos. payments.php converts it to centavos for PayMongo.
  `price`       DECIMAL(10,2) NOT NULL,
  -- Relative path used as a CSS background-image in the templates.
  `image`       VARCHAR(255)  NOT NULL,
  `max_guests`  TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rooms_price` (`price`),
  KEY `idx_rooms_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `room_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT UNSIGNED NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_room_images_room` (`room_id`),
  CONSTRAINT `fk_room_images_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- bookings: one row per reservation.
--
-- status          'Pending' | 'Confirmed' | 'Cancelled' | ...
--                 set to 'Pending' on insert, flipped to 'Confirmed' by
--                 payments.php mark_booking_paid(), guarded so a cancelled
--                 booking is never silently re-confirmed.
-- payment_status  'Unpaid' | 'Pending Verification' | 'Paid'
--                 'Pending Verification' = guest sent a manual GCash/bank
--                 reference; 'Paid' = settled (online or verified by staff).
-- ---------------------------------------------------------------
CREATE TABLE `bookings` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id`            INT UNSIGNED NOT NULL,
  `guest_name`         VARCHAR(120) NOT NULL,
  `email`              VARCHAR(190) NOT NULL,
  `phone`              VARCHAR(40)  NOT NULL,
  `check_in`           DATE         NOT NULL,
  `check_out`          DATE         NOT NULL,
  `guests`             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `children`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `seniors`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `special_requests`   TEXT DEFAULT NULL,
  `booking_reference`  VARCHAR(20) NOT NULL,
  `price_per_night`    DECIMAL(10,2) NOT NULL,
  `total_amount`       DECIMAL(10,2) NOT NULL,
  `expires_at`         DATETIME DEFAULT NULL,
  `status`             VARCHAR(30)  NOT NULL DEFAULT 'Pending',
  `payment_status`     VARCHAR(30)  NOT NULL DEFAULT 'Unpaid',
  `payment_method`     VARCHAR(40)  DEFAULT NULL,
  `payment_reference`  VARCHAR(120) DEFAULT NULL,
  -- PayMongo checkout session id (chkcs_...), used to re-verify the payment
  -- from payment_return.php / the webhook.
  `payment_session_id` VARCHAR(80)  DEFAULT NULL,
  `paid_at`            DATETIME     DEFAULT NULL,
  `notes`              TEXT         DEFAULT NULL,
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookings_reference` (`booking_reference`),
  KEY `idx_bookings_room`    (`room_id`),
  -- Supports the double-booking overlap check in booking.php
  -- (room_id + status + date range) and the admin listing filters.
  KEY `idx_bookings_avail`   (`room_id`, `status`, `check_in`, `check_out`),
  KEY `idx_bookings_status`  (`status`),
  KEY `idx_bookings_payment` (`payment_status`),
  KEY `idx_bookings_email`   (`email`),
  CONSTRAINT `fk_bookings_room`
    FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default password: password (change it before deployment).
INSERT INTO `admin_users` (`username`, `password_hash`) VALUES
('admin', '$2y$10$82DKL2Df3ngtR02m8J7w5uNEtw9I0Leym7m90D8yFUmbpqBu.iipe');

CREATE TABLE `status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `payment_status` VARCHAR(30) NOT NULL,
  `changed_by` VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_history_booking` (`booking_id`),
  CONSTRAINT `fk_history_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `invoice_number` VARCHAR(32) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PHP',
  `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_invoice_booking` (`booking_id`), UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  CONSTRAINT `fk_invoice_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payment_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(30) NOT NULL,
  `provider_reference` VARCHAR(120) DEFAULT NULL,
  `method` VARCHAR(40) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `raw_payload` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_transactions_booking` (`booking_id`),
  CONSTRAINT `fk_transactions_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED DEFAULT NULL,
  `recipient` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `html_body` MEDIUMTEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Queued',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT DEFAULT NULL,
  `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_email_queue_status` (`status`,`available_at`),
  CONSTRAINT `fk_email_queue_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor` VARCHAR(120) NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `details` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_audit_entity` (`entity_type`,`entity_id`), KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Sample rooms, so the site is usable immediately.
-- Ordered by price DESC in the UI, hence the ids.
-- ---------------------------------------------------------------
INSERT INTO `rooms` (`name`, `category`, `description`, `price`, `image`, `max_guests`) VALUES
  ('The Penthouse Suite', 'Signature Suite',
   'Our top-floor residence with a private rooftop terrace, panoramic city views, a separate living room and a dedicated butler.', 28500.00, 'images/room-penthouse.svg', 6),
  ('Royal Deluxe King', 'Deluxe Room',
   'A refined king room with a lounge area, marble bathroom and floor-to-ceiling windows over the city skyline.', 18500.00, 'images/room-royal-deluxe.svg', 3),
  ('Premier Twin', 'Deluxe Room',
   'Two plush twin beds, a work desk and a rain shower - ideal for colleagues travelling together.', 15200.00, 'images/room-premier-twin.svg', 3),
  ('Garden View Standard', 'Standard Room',
   'A calm, thoughtfully designed room opening onto the hotel garden, with a queen bed and modern work area.', 11800.00, 'images/room-garden-standard.svg', 2),
  ('Executive Studio', 'Studio',
   'A compact executive layout with a kitchenette, lounge and city view - well suited to longer stays.', 16400.00, 'images/room-executive-studio.svg', 2),
  ('Grand Deluxe Suite', 'Signature Suite',
   'A spacious suite with a separate bedroom, living area and generous bathroom, plus complimentary airport transfer.', 24500.00, 'images/room-grand-deluxe.svg', 5);
