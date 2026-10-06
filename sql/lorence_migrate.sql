-- Non-destructive migration for an existing LORENCE installation.
USE `lorence_hotel`;
CREATE TABLE IF NOT EXISTS `room_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` INT UNSIGNED NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_room_images_room` (`room_id`),
  CONSTRAINT `fk_room_images_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE bookings
  ADD COLUMN children TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER guests,
  ADD COLUMN seniors TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER children,
  ADD COLUMN special_requests TEXT NULL AFTER seniors,
  ADD COLUMN booking_reference VARCHAR(20) NULL AFTER special_requests,
  ADD COLUMN price_per_night DECIMAL(10,2) NULL AFTER booking_reference,
  ADD COLUMN total_amount DECIMAL(10,2) NULL AFTER price_per_night,
  ADD COLUMN expires_at DATETIME NULL AFTER total_amount;
UPDATE bookings b JOIN rooms r ON r.id=b.room_id SET b.booking_reference=CONCAT('LRC-',YEAR(b.created_at),'-',LPAD(UPPER(HEX(b.id)),5,'0')), b.price_per_night=r.price, b.total_amount=DATEDIFF(b.check_out,b.check_in)*r.price WHERE b.booking_reference IS NULL;
ALTER TABLE bookings MODIFY booking_reference VARCHAR(20) NOT NULL, MODIFY price_per_night DECIMAL(10,2) NOT NULL, MODIFY total_amount DECIMAL(10,2) NOT NULL, ADD UNIQUE KEY uq_bookings_reference (booking_reference), ADD KEY idx_bookings_expiry (expires_at);
CREATE TABLE IF NOT EXISTS admin_users (id INT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(80) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uq_admin_username(username)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO admin_users(username,password_hash) VALUES ('admin','$2y$10$82DKL2Df3ngtR02m8J7w5uNEtw9I0Leym7m90D8yFUmbpqBu.iipe');
CREATE TABLE IF NOT EXISTS status_history (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, booking_id INT UNSIGNED NOT NULL, status VARCHAR(30) NOT NULL, payment_status VARCHAR(30) NOT NULL, changed_by VARCHAR(120) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_history_booking(booking_id), CONSTRAINT fk_history_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS invoices (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, booking_id INT UNSIGNED NOT NULL, invoice_number VARCHAR(32) NOT NULL, amount DECIMAL(10,2) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'PHP', issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, pdf_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id), UNIQUE KEY uq_invoice_booking(booking_id), UNIQUE KEY uq_invoice_number(invoice_number), CONSTRAINT fk_invoice_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payment_transactions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, booking_id INT UNSIGNED NOT NULL, provider VARCHAR(30) NOT NULL, provider_reference VARCHAR(120) DEFAULT NULL, method VARCHAR(40) DEFAULT NULL, amount DECIMAL(10,2) NOT NULL, status VARCHAR(30) NOT NULL, raw_payload JSON DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_transactions_booking(booking_id), CONSTRAINT fk_transactions_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS email_queue (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, booking_id INT UNSIGNED DEFAULT NULL, recipient VARCHAR(190) NOT NULL, subject VARCHAR(255) NOT NULL, html_body MEDIUMTEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Queued', attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, last_error TEXT DEFAULT NULL, available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_email_queue_status(status,available_at), CONSTRAINT fk_email_queue_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_log (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, actor VARCHAR(120) NOT NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id BIGINT UNSIGNED DEFAULT NULL, details JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_audit_entity(entity_type,entity_id), KEY idx_audit_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
