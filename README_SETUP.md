# LORENCE — email, online payment, receipt, spatial design

## 1. Database
For a new install, import `sql/lorence_setup.sql`. For an existing install with bookings, import `sql/lorence_migrate.sql` once; it preserves current reservations and backfills their price snapshot and reference. Existing installations that already completed that migration should additionally import `sql/lorence_room_images_migrate.sql` and `sql/lorence_operational_migrate.sql` for the room gallery and operational tables.

## 2. config.php (new settings at the bottom)
- `SITE_URL` – the real address of the site (used in email links and payment redirects).
- `MAIL_DRIVER` – starts as `log`: emails are saved as .html files in `storage/mail/` so you can preview them.
  Set to `smtp` and fill `MAIL_HOST/PORT/SECURE/USER/PASS` to really send (Gmail needs an App Password).
- `PAYMONGO_SECRET_KEY` – paste your `sk_test_...` key to turn on online payment (GCash, Maya, GrabPay, cards).
  Leave empty to keep only the manual GCash/bank flow.
- `PAYMONGO_WEBHOOK_SECRET` – in the PayMongo dashboard create a webhook to
  `https://YOUR-DOMAIN/paymongo_webhook.php` for `checkout_session.payment.paid` and paste its `whsk_...` secret.
  On localhost the webhook can't reach you, but `payment_return.php` still confirms the payment when the guest comes back.

## 3. Flow
reserve -> email "complete payment" -> pay online (auto-confirmed + receipt email)
        or manual reference -> email "under review" -> admin MARK PAID -> receipt email.
Receipt page: `receipt.php` (print / save as PDF / email a copy).

Staff room management is available at `/admin/rooms.php` after signing in. Staff can create,
edit, hide, and remove rooms and upload up to 10 JPG, PNG, WEBP, or GIF images per room.
Paid bookings automatically use the receipt as the invoice and include the unique booking
reference; guests can recover and track it from `/find.php` using that reference and email.

## 4. Before going live
Change the default admin password (`admin` / `password`) before deployment, set a MySQL password,
configure `BOOKING_TOKEN_SECRET` as an environment variable, and keep `storage/mail/` and `sql/`
outside the public web root where possible. The schema now stores a unique booking reference,
price snapshot, total, guest breakdown, expiry hold, admin users, and status history.
