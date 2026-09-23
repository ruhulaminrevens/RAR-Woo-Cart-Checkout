# RAR Woo Cart & Checkout

A production-focused WooCommerce cart and checkout UX plugin that consolidates two previously separate customizations into one manageable plugin:

1. **Bangladesh Address UX** — streamlined billing fields, Bangladesh-only country handling, searchable district-aware Town/City selection, Cart shipping calculator integration, and configurable Order Notes.
2. **Express Buy Now** — a compact same-origin checkout modal for supported Buy Now buttons with safe fallback to the theme’s native behavior.

## Download

⬇️ **[Download Latest Installable ZIP](https://raw.githubusercontent.com/ruhulaminrevens/RAR-Woo-Cart-Checkout/main/releases/RAR-Woo-Cart-Checkout-v1.0.0.zip)**

Current stable version: **v1.0.0**

### Install

`WordPress → Plugins → Add New → Upload Plugin → choose ZIP → Install Now → Activate`

Then open:

`WooCommerce → RAR Cart & Checkout`

## Main features

### Bangladesh Address UX
- Keeps Bangladesh selected internally and can hide Country / Region.
- Full name, Phone, Email, Street address, Town / City and District layout.
- Optional removal of Last name, Company, Address line 2 and Postcode.
- District-aware searchable Town / City selector.
- 64-district / 559-location dataset.
- Searchable Town / City on Checkout and Cart shipping calculator.
- Optional hiding of “Ship to a different address?”.
- Additional Information / Order notes can remain visible.
- Labels and headings are editable from the admin panel.

### Express Buy Now
- Default compatibility with Woodmart `.wd-buy-now-btn`.
- Buy Now button selector can be edited from admin.
- Same-origin checkout modal.
- Product is submitted through the original WooCommerce product form.
- Variation validation is respected.
- External and grouped products fall back to the theme behavior.
- Full checkout remains available as a fallback link.
- Modal text, headings, color and behavior are configurable.
- Normal checkout and Express checkout can use different Additional Information visibility.

## Admin controls

`WooCommerce → RAR Cart & Checkout`

You can enable/disable and edit:
- Checkout billing UX
- Cart shipping calculator UX
- Bangladesh-only country behavior
- Optional field removal
- Ship-to-different-address visibility
- Order Notes visibility
- Searchable Town / City
- Checkout field labels/headings
- Express Buy Now
- Buy Now selector
- Checkout path
- Modal title/subtitle/footer
- Express checkout headings
- Express modal Additional Information visibility
- Primary color

## Safe migration from WPCode

If these features are currently running as WPCode snippets:

1. Install and **activate this plugin first**.
2. Open `WooCommerce → RAR Cart & Checkout` and confirm both required modules are enabled.
3. Deactivate the old **Checkout / Bangladesh Address UX** WPCode snippet.
4. Deactivate the old **Express Buy Now** WPCode snippet.
5. Clear page/cache/CDN caches.
6. Test:
   - Cart shipping calculator
   - Checkout address fields
   - District → Town / City search
   - Additional Information / Order notes
   - Product Buy Now modal
   - Simple product
   - Variable product
   - Place Order and order-received redirect
7. Only after successful testing, delete the old WPCode snippets.

The plugin includes a migration guard for the old Express Buy Now browser script to reduce duplicate event handlers during the transition. The old PHP checkout snippet should still be disabled immediately after activating this plugin to avoid duplicate WooCommerce filters.

## Compatibility

- WordPress 6.5+
- PHP 8.0+
- WooCommerce 8.5+
- Tested target: WooCommerce 11.1.x
- HPOS compatible
- **Classic Cart / Checkout** supported
- WooCommerce Cart/Checkout Blocks are not declared compatible in v1.0.0
- Express Buy Now default selector targets Woodmart but can be edited for another theme

## Data attribution

The Bangladesh administrative Town/City dataset was carried over from the existing implementation and is attributed to:

`open-admin-data/bangladesh-administrative-divisions` — CC BY 4.0.

See `ATTRIBUTION.md`.

## Repository structure

```text
assets/
  css/
    address.css
    express.css
  data/
    bd-cities.json
  js/
    address.js
    express.js
includes/
  class-rar-wcc-address.php
  class-rar-wcc-express.php
  class-rar-wcc-settings.php
rar-woo-cart-checkout.php
README.md
readme.txt
CHANGELOG.md
ATTRIBUTION.md
uninstall.php
releases/
  RAR-Woo-Cart-Checkout-v1.0.0.zip
```

## Safety

The plugin does not change order/payment data and does not delete WooCommerce data. Disabling either module from the admin panel restores WooCommerce/theme behavior for that module. Uninstall intentionally preserves the plugin settings option so an accidental uninstall/reinstall does not erase configuration.
