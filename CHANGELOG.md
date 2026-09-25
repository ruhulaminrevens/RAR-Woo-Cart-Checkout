# Changelog

## v2.0.0 — 2026-09-26

Major upgrade: from a checkout UX tweak to a complete Bangladesh cart & checkout suite.

### Fixed
- **Chapai Nawabganj** district never loaded its towns (WooCommerce names it “Nawabganj”); all 64 districts now resolve.
- Custom Full name / Town / District labels were reverted by WooCommerce’s address script after load.
- Express Buy Now could open checkout with an empty cart (fixed 4.5 s timer after a hidden form post); now waits for a confirmed AJAX add-to-cart.
- Express Buy Now tapped twice doubled the quantity.
- Online payment gateways (bKash/Nagad/SSLCommerz…) opened inside the modal iframe and failed; every post-order redirect now happens at top level.
- Closing the modal reloaded the whole product page.
- Place Order inside the modal no longer depends on text-matching headings in JavaScript (server-side headings, no flicker).
- Shipping address is now filled from billing so courier plugins always receive a full address.

### Added
- Admin area **RAR Checkout**: Dashboard, Incomplete Orders, tabbed Settings, Tools & Status.
- Dashboard KPIs, 14-day chart, call list, health check with one-click fixes (Blocks → Classic switch with backup/restore, duplicate WPCode snippet detection).
- Bangladeshi mobile validation & normalisation (Bangla digits, +880), live operator hint.
- Optional email, field-order choice, strict Town/District validation, typed custom areas, extra areas per district, Dhaka & Chattogram metro thanas.
- Incomplete-order capture & recovery (statuses, notes, CSV, WhatsApp template, one-click order creation, auto-close on order, retention cleanup).
- Order protection: blocklist (phone/email/IP wildcards), cooldown, minimum order, max COD, minimum success rate for COD, blocked-attempt log.
- Customer Insight meta box, Customer score column, Block/Unblock from the order screen.
- Checkout quantity editor; free-delivery progress bar with automatic free-rate pre-selection; Place Order `{total}` text; trust line; coupon text.
- Own Buy Now button for non-Woodmart themes; “buy only this product” mode; mobile bottom-sheet modal with focus trap.
- REST API `rar-wcc/v1` (stats, incomplete, orders, customer, block).
- Settings export/import/reset, Bangla/English label presets, cache & cleanup tools.
- Translation template (`languages/rar-woo-cart-checkout.pot`), developer hooks.
- Optional data removal on uninstall.

### Changed
- Default field order is now Name → Phone → District → Town/City → Address → Email (switchable back under Settings → Address & Fields).
- Express checkout path defaults to the WooCommerce checkout page (override still available).

## v1.0.0 — 2026-09-23

Initial stable release (Bangladesh address UX, searchable Town/City, Express Buy Now modal, HPOS declaration, CI ZIP build).
