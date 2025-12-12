=== WC Price History Dev Tools ===
Contributors: custom
Tags: woocommerce, price history, import
Requires at least: 5.8
Tested up to: 6.8.2
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dev Tools for WC Price History.

== Description ==

WC Price History Import allows you to import price history data that was previously exported from the WC Price History plugin. This is useful for:

* Migrating price history between products
* Restoring price history after product changes
* Transferring price data between different WooCommerce installations

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wc-ph-import` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Make sure WooCommerce and WC Price History plugins are installed and active

== Usage ==

1. Go to **Tools > WC Price History Import** in your WordPress admin
2. Select the target product where you want to import the price history
3. Choose the JSON file exported from WC Price History plugin
4. Click "Import Data"

The plugin will:
* Parse the exported JSON data
* Import price history for the main product
* Import price history for product variations (if applicable)
* Match variations by their attributes

== Requirements ==

* WooCommerce plugin
* WC Price History plugin
* WordPress 5.8 or higher
* PHP 7.2 or higher

== Changelog ==

= 1.0.0 =
* Initial release
* Import price history from JSON files
* Support for variable products and variations
* Admin interface under Tools menu

