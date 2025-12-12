<?php
/**
 * Plugin Name: WC Price History Dev Tools
 * Description: Dev Tools for WC Price History
 * Author: Custom Development
 * Version: 1.0.0
 * Text Domain: wc-ph-devtools
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce, wc-price-history
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Load Composer autoloader.
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
}

// Check if required plugins are active
add_action('admin_init', function() {
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', function() {
			echo '<div class="notice notice-error"><p>' .
				 esc_html__('WC Price History Import requires WooCommerce to be installed and active.', 'wc-ph-import') .
				 '</p></div>';
		});
	}

	if (!class_exists('PriorPrice\HistoryStorage')) {
		add_action('admin_notices', function() {
			echo '<div class="notice notice-error"><p>' .
				 esc_html__('WC Price History Import requires WC Price History plugin to be installed and active.', 'wc-ph-import') .
				 '</p></div>';
		});
	}
});

// Initialize the plugin
add_action('init', function() {
	if (class_exists('WooCommerce') && class_exists('PriorPrice\HistoryStorage')) {
		new \WcPriceHistory\WcphDevtools\HistoryImport();
		new \WcPriceHistory\WcphDevtools\AllHistoryExporter();
	}
});
