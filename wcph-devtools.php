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
		new WC_Price_History_Import();
	}
});

class WC_Price_History_Import {

	public function __construct() {
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_wc_ph_import_data', [$this, 'handle_import']);
	}

	/**
	 * Add admin menu under Tools
	 */
	public function add_admin_menu() {
		add_management_page(
			'WC Price History Import',
			'WC Price History Import',
			'manage_woocommerce',
			'wc-ph-import',
			[$this, 'admin_page']
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_scripts($hook) {
		if ($hook !== 'tools_page_wc-ph-import') {
			return;
		}

		wp_enqueue_script('jquery');
		wp_add_inline_script('jquery', $this->get_admin_js());
		wp_add_inline_style('wp-admin', $this->get_admin_css());
	}

	/**
	 * Admin page content
	 */
	public function admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e('WC Price History Import', 'wc-ph-import'); ?></h1>

			<div class="card">
				<h2><?php esc_html_e('Import Price History Data', 'wc-ph-import'); ?></h2>

				<form id="wc-ph-import-form" enctype="multipart/form-data">
					<?php wp_nonce_field('wc_ph_import_nonce', 'wc_ph_import_nonce'); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="target_product"><?php esc_html_e('Target Product', 'wc-ph-import'); ?></label>
							</th>
							<td>
								<select id="target_product" name="target_product" required>
									<option value=""><?php esc_html_e('Select a product...', 'wc-ph-import'); ?></option>
									<?php $this->render_product_options(); ?>
								</select>
								<p class="description">
									<?php esc_html_e('Select the product to which you want to import the price history data.', 'wc-ph-import'); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="import_file"><?php esc_html_e('Import File', 'wc-ph-import'); ?></label>
							</th>
							<td>
								<input type="file" id="import_file" name="import_file" accept=".json" required />
								<p class="description">
									<?php esc_html_e('Select the JSON file exported from WC Price History plugin.', 'wc-ph-import'); ?>
								</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<input type="submit" class="button-primary" value="<?php esc_attr_e('Import Data', 'wc-ph-import'); ?>" />
					</p>
				</form>

				<div id="import-result" style="display: none;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render product options for select dropdown
	 */
	private function render_product_options() {
		$products = wc_get_products([
			'limit' => -1,
			'status' => 'publish',
			'orderby' => 'title',
			'order' => 'ASC'
		]);

		foreach ($products as $product) {
			echo '<option value="' . esc_attr($product->get_id()) . '">';
			echo esc_html($product->get_name() . ' (ID: ' . $product->get_id() . ')');
			echo '</option>';
		}
	}

	/**
	 * Handle AJAX import request
	 */
	public function handle_import() {
		// Verify nonce
		if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_ph_import_nonce')) {
			wp_send_json_error(['message' => __('Invalid nonce', 'wc-ph-import')]);
		}

		// Check permissions
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('You do not have permission to import data', 'wc-ph-import')]);
		}

		$target_product_id = intval($_POST['target_product'] ?? 0);
		$import_data = wp_unslash($_POST['import_data'] ?? '');

		if (!$target_product_id) {
			wp_send_json_error(['message' => __('Please select a target product', 'wc-ph-import')]);
		}

		if (!$import_data) {
			wp_send_json_error(['message' => __('No import data provided', 'wc-ph-import')]);
		}

		try {
			$result = $this->import_price_history($target_product_id, $import_data);
			wp_send_json_success($result);
		} catch (Exception $e) {
			wp_send_json_error(['message' => $e->getMessage()]);
		}
	}

	/**
	 * Import price history data
	 */
	private function import_price_history($target_product_id, $import_data) {
		// Decode the JSON data
		$data = json_decode($import_data, true);

		if (!$data) {
			throw new Exception(__('Invalid JSON data', 'wc-ph-import'));
		}

		// Check if the data has the expected structure
		if (!isset($data['product_name']) || !isset($data['serialized'])) {
			throw new Exception(__('Invalid data format. Expected product_name and serialized fields.', 'wc-ph-import'));
		}

		// Unserialize the data
		$unserialized_data = unserialize($data['serialized']);

		if (!$unserialized_data) {
			throw new Exception(__('Failed to unserialize data', 'wc-ph-import'));
		}

		// Get the target product
		$target_product = wc_get_product($target_product_id);
		if (!$target_product) {
			throw new Exception(__('Target product not found', 'wc-ph-import'));
		}

		$history_storage = new PriorPrice\HistoryStorage();
		$imported_count = 0;

		// Import main product history
		if (isset($unserialized_data['product']['history']) && is_array($unserialized_data['product']['history'])) {
			foreach ($unserialized_data['product']['history'] as $timestamp => $price) {
				$history_storage->add_historical_price($target_product_id, (float)$price, (int)$timestamp);
				$imported_count++;
			}
		}

		// Import variations if target product is variable and source has variations
		if ($target_product->is_type('variable') && isset($unserialized_data['variations'])) {
			$variations = $target_product->get_children();

			foreach ($unserialized_data['variations'] as $variation_data) {
				if (isset($variation_data['history']) && is_array($variation_data['history'])) {
					// Find matching variation by attributes
					$matching_variation_id = $this->find_matching_variation($target_product, $variation_data['attributes']);

					if ($matching_variation_id) {
						foreach ($variation_data['history'] as $timestamp => $price) {
							$history_storage->add_historical_price($matching_variation_id, (float)$price, (int)$timestamp);
							$imported_count++;
						}
					}
				}
			}
		}

		return [
			'message' => sprintf(
				__('Successfully imported %d price history entries for product "%s"', 'wc-ph-import'),
				$imported_count,
				$target_product->get_name()
			),
			'imported_count' => $imported_count
		];
	}

	/**
	 * Find matching variation by attributes
	 */
	private function find_matching_variation($product, $source_attributes) {
		if (!$product->is_type('variable')) {
			return false;
		}

		$variations = $product->get_available_variations('objects');

		foreach ($variations as $variation) {
			$variation_attributes = $variation->get_attributes();
			$match = true;

			foreach ($source_attributes as $attr_name => $attr_value) {
				if (isset($variation_attributes[$attr_name])) {
					$variation_value = $variation_attributes[$attr_name];

					// Handle different attribute value formats
					if (is_array($attr_value)) {
						// Skip complex attribute objects
						continue;
					}

					if ($variation_value !== $attr_value) {
						$match = false;
						break;
					}
				}
			}

			if ($match) {
				return $variation->get_id();
			}
		}

		return false;
	}

	/**
	 * Get admin JavaScript
	 */
	private function get_admin_js() {
		return "
		jQuery(document).ready(function($) {
			$('#wc-ph-import-form').on('submit', function(e) {
				e.preventDefault();

				var formData = new FormData();
				var fileInput = $('#import_file')[0];
				var targetProduct = $('#target_product').val();

				if (!targetProduct) {
					alert('Please select a target product');
					return;
				}

				if (!fileInput.files[0]) {
					alert('Please select a file to import');
					return;
				}

				var file = fileInput.files[0];
				var reader = new FileReader();

				reader.onload = function(e) {
					formData.append('action', 'wc_ph_import_data');
					formData.append('nonce', $('#wc_ph_import_nonce').val());
					formData.append('target_product', targetProduct);
					formData.append('import_data', e.target.result);

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function(response) {
							if (response.success) {
								$('#import-result').html('<div class=\"notice notice-success\"><p>' + response.data.message + '</p></div>').show();
							} else {
								$('#import-result').html('<div class=\"notice notice-error\"><p>' + response.data.message + '</p></div>').show();
							}
						},
						error: function() {
							$('#import-result').html('<div class=\"notice notice-error\"><p>An error occurred during import</p></div>').show();
						}
					});
				};

				reader.readAsText(file);
			});
		});
		";
	}

	/**
	 * Get admin CSS
	 */
	private function get_admin_css() {
		return "
		.wc-ph-import-form {
			max-width: 600px;
		}

		.wc-ph-import-form .form-table th {
			width: 200px;
		}

		#import-result {
			margin-top: 20px;
		}

		#import-result .notice {
			margin: 0;
		}
		";
	}
}

