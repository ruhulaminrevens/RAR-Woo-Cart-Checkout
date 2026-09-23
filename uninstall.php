<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Intentionally preserve rar_wcc_settings.
// This prevents accidental loss of configuration on uninstall/reinstall.
// WooCommerce/order/customer data is never removed by this plugin.
