# RAR Woo Cart & Checkout

**Bangladesh-first WooCommerce cart & checkout suite** — smart address fields, BD mobile validation, Express Buy Now modal, incomplete-order recovery, fraud/duplicate protection, customer success-rate insight, free-delivery progress, checkout quantity editing, a live dashboard and a REST API for staff apps.

Built for [nabiad.com](https://www.nabiad.com/) (Woodmart theme, Cash-on-Delivery, nationwide delivery) and works with any classic WooCommerce theme.

## Download

⬇️ **[Download installable ZIP v2.0.0](https://raw.githubusercontent.com/ruhulaminrevens/RAR-Woo-Cart-Checkout/main/releases/RAR-Woo-Cart-Checkout-v2.0.0.zip)**

`WordPress → Plugins → Add New → Upload Plugin → choose ZIP → Install Now → Activate`

Upgrading from v1.0.0: upload the new ZIP and choose **Replace current with uploaded**. Settings are kept.

Then open **RAR Checkout** in the admin menu.

---

## What's inside

### 📊 Dashboard (RAR Checkout → Dashboard)
- Orders today / 30 days, Express Buy Now orders & share, recovered orders, open-cart value, recovery rate, blocked attempts.
- 14-day chart: orders, Express, recovered, incomplete checkouts.
- **“Call these customers”** list with one-tap Call / WhatsApp.
- **Health check with one-click fixes**: Blocks → Classic checkout switch (reversible), duplicate WPCode snippets, COD status, theme/Buy Now selector, free-delivery threshold, cron.

### 📍 Bangladesh address & phone
- Bangladesh-only country, Full-name/Phone/District/Town/Address layout, optional email.
- Searchable **District → Town/City** selector (64 districts, 630+ areas incl. Dhaka & Chattogram metro thanas).
- Customers can type an unlisted area; admins can add extra areas per district.
- **BD mobile validation**: accepts `01…`, `+880…`, spaces/dashes and **Bangla digits (০-৯)**; saves a clean `01XXXXXXXXX` (or `+880…`).
- Live operator hint (“✓ Grameenphone”, “✓ Robi”…).
- Optional strict “Town must belong to District” validation.
- Labels keep working after WooCommerce’s address script re-renders (v1 bug fixed).
- One-click **Bangla label preset**.

### ⚡ Express Buy Now
- Adds the product **in the background** (AJAX) then opens checkout in a modal — no more empty-cart race.
- Works with Woodmart `.wd-buy-now-btn`, any custom selector, or **our own Buy Now button** for other themes.
- Variations, quantity and product add-on fields are submitted; double-tap doesn’t double quantity.
- Option: **buy only this product** (clear cart first).
- After ordering — including online gateways (bKash/Nagad/SSLCommerz) — the customer lands on the real thank-you/payment page, not inside the modal.
- Mobile full-screen sheet, focus trap, Esc to close, slow-network fallback, retry on error.
- Orders are tagged **Express** and shown with ⚡ in the Orders list.

### 🛒 Cart & checkout boosters
- − / + / remove controls in the checkout order summary (and in the modal).
- **Free-delivery progress bar** (auto-detects your Free-shipping minimum) in Cart, Checkout, modal and mini-cart; free delivery is **pre-selected** the moment it unlocks.
- Custom Place-Order text with live `{total}`, trust line, custom coupon text.

### 📞 Incomplete-order recovery
- Captures name, phone, address, district/city and cart as soon as a **valid phone** is typed.
- Staff list with statuses (New, Contacted, Call back, Recovered, Ordered by customer, Not interested, Spam), notes, search, filters, CSV export (Excel-safe Bangla).
- **Create order in one click** from a record.
- Records close automatically when the customer orders (Contacted → *Recovered*).
- Pre-filled WhatsApp message template; auto-delete after N days.

### 🛡️ Order protection & customer insight
- **Customer Insight** box on every order: total/delivered/cancelled, success rate, risk label, previous orders, Call/WhatsApp, **Block/Unblock**.
- **Customer score** column in Orders list.
- Rules: blocklist (phone/email/IP with wildcards), duplicate-order cooldown, minimum order, max COD total, minimum success rate for COD.
- Recent blocked attempts on the dashboard.

### 🔌 REST API (`/wp-json/rar-wcc/v1`)
Use with Application Passwords (e.g. from the RAR Woo Stock & Order staff app).

| Method | Route | Purpose |
|---|---|---|
| GET | `/stats` | Dashboard numbers & 14-day series |
| GET | `/incomplete?status=open&search=&page=` | List incomplete checkouts |
| GET/POST/DELETE | `/incomplete/{id}` | Read, update `status`/`admin_note`, delete |
| POST | `/incomplete/{id}/order` | Create a WooCommerce order |
| GET | `/customer?phone=01…` | Customer history & risk |
| POST | `/customer/block` | Block/unblock a phone |

### 🧩 Developer hooks
`rar_wcc_loaded`, `rar_wcc_guard_validate( $errors, $data )`, `rar_wcc_incomplete_captured`, `rar_wcc_incomplete_status_changed`, `rar_wcc_incomplete_order_created`, `rar_wcc_settings_saved`; filters `rar_wcc_city_map`, `rar_wcc_free_shipping_threshold`, `rar_wcc_success_statuses`, `rar_wcc_failed_statuses`, `rar_wcc_settings_schema`.

---

## Requirements
- WordPress 6.5+, PHP 8.0+, WooCommerce 8.5+ (tested to 10.2).
- **Classic** Cart & Checkout (the dashboard switches Blocks → Classic in one click, reversible).
- HPOS and legacy order storage both supported.

## Migrating from WPCode snippets
Activate the plugin, then deactivate the old checkout/address and Express Buy Now snippets. The Health check lists any snippet that still looks active.

## Privacy
Incomplete-checkout data is collected only after a valid phone number is typed, stays in your database (`wp_rar_wcc_incomplete`), and is auto-deleted after the retention period. Mention order follow-up calls in your privacy policy.

## License
GPLv2 or later. Location dataset: see [ATTRIBUTION.md](ATTRIBUTION.md).
