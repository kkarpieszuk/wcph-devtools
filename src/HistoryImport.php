<?php

namespace WcPriceHistory\WcphDevtools;

use Exception;
use PriorPrice\HistoryStorage;

class HistoryImport {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_wc_ph_import_data', [ $this, 'handle_import' ] );
	}

	/**
	 * Add admin menu under Tools.
	 */
	public function add_admin_menu(): void {
		add_management_page(
			'WC Price History Dev Tools',
			'WC Price History Dev Tools',
			'manage_woocommerce',
			'wc-ph-import',
			[ $this, 'admin_page' ]
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( $hook !== 'tools_page_wc-ph-import' ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/wcph-devtools.php' );
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script(
			'wc-ph-devtools-history-import',
			$plugin_url . 'assets/js/history-import.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);
		wp_add_inline_style( 'wp-admin', $this->get_admin_css() );
	}

	/**
	 * Admin page content.
	 */
	public function admin_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WC Price History Dev Tools', 'wc-ph-import' ); ?></h1>

			<div class="card">
				<h2><?php esc_html_e( 'Import Price History Data', 'wc-ph-import' ); ?></h2>

				<form id="wc-ph-import-form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'wc_ph_import_nonce', 'wc_ph_import_nonce' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="target_product"><?php esc_html_e( 'Target Product', 'wc-ph-import' ); ?></label>
							</th>
							<td>
								<select id="target_product" name="target_product" required>
									<option value=""><?php esc_html_e( 'Select a product...', 'wc-ph-import' ); ?></option>
									<?php $this->render_product_options(); ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the product to which you want to import the price history data.', 'wc-ph-import' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="import_file"><?php esc_html_e( 'Import File', 'wc-ph-import' ); ?></label>
							</th>
							<td>
								<input type="file" id="import_file" name="import_file" accept=".json" required />
								<p class="description">
									<?php esc_html_e( 'Select the JSON file exported from WC Price History plugin.', 'wc-ph-import' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Import Data', 'wc-ph-import' ); ?>" />
					</p>
				</form>

				<div id="import-result" style="display: none;"></div>
			</div>

			<?php
			do_action( 'wc_ph_devtools_admin_page_after_import' );
			?>
		</div>
		<?php
	}

	/**
	 * Render product options for select dropdown.
	 */
	private function render_product_options(): void {
		$products = wc_get_products(
			[
				'limit'   => -1,
				'status'  => 'publish',
				'orderby' => 'title',
				'order'   => 'ASC',
			]
		);

		foreach ( $products as $product ) {
			echo '<option value="' . esc_attr( $product->get_id() ) . '">';
			echo esc_html( $product->get_name() . ' (ID: ' . $product->get_id() . ')' );
			echo '</option>';
		}
	}

	/**
	 * Handle AJAX import request.
	 */
	public function handle_import(): void {
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wc_ph_import_nonce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'wc-ph-import' ) ] );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to import data', 'wc-ph-import' ) ] );
		}

		$target_product_id = intval( $_POST['target_product'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$import_data       = wp_unslash( $_POST['import_data'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $target_product_id ) {
			wp_send_json_error( [ 'message' => __( 'Please select a target product', 'wc-ph-import' ) ] );
		}

		if ( ! $import_data ) {
			wp_send_json_error( [ 'message' => __( 'No import data provided', 'wc-ph-import' ) ] );
		}

		try {
			$result = $this->import_price_history( $target_product_id, $import_data );
			wp_send_json_success( $result );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Import price history data.
	 *
	 * @param int    $target_product_id Target product ID.
	 * @param string $import_data       Raw JSON import data.
	 *
	 * @return array<string, mixed>
	 */
	private function import_price_history( int $target_product_id, string $import_data ): array {
		$data = json_decode( $import_data, true );

		if ( ! $data ) {
			throw new Exception( __( 'Invalid JSON data', 'wc-ph-import' ) );
		}

		if ( ! isset( $data['product_name'], $data['serialized'] ) ) {
			throw new Exception( __( 'Invalid data format. Expected product_name and serialized fields.', 'wc-ph-import' ) );
		}

		$unserialized_data = unserialize( $data['serialized'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		if ( ! $unserialized_data ) {
			throw new Exception( __( 'Failed to unserialize data', 'wc-ph-import' ) );
		}

		$target_product = wc_get_product( $target_product_id );
		if ( ! $target_product ) {
			throw new Exception( __( 'Target product not found', 'wc-ph-import' ) );
		}

		$history_storage = new HistoryStorage();
		$imported_count  = 0;

		if ( isset( $unserialized_data['product']['history'] ) && is_array( $unserialized_data['product']['history'] ) ) {
			foreach ( $unserialized_data['product']['history'] as $timestamp => $price ) {
				$history_storage->add_historical_price( $target_product_id, (float) $price, (int) $timestamp );
				++$imported_count;
			}
		}

		if ( $target_product->is_type( 'variable' ) && isset( $unserialized_data['variations'] ) ) {
			foreach ( $unserialized_data['variations'] as $variation_data ) {
				if ( isset( $variation_data['history'] ) && is_array( $variation_data['history'] ) ) {
					$matching_variation_id = $this->find_matching_variation( $target_product, $variation_data['attributes'] );

					if ( $matching_variation_id ) {
						foreach ( $variation_data['history'] as $timestamp => $price ) {
							$history_storage->add_historical_price( $matching_variation_id, (float) $price, (int) $timestamp );
							++$imported_count;
						}
					}
				}
			}
		}

		return [
			'message'        => sprintf(
				__( 'Successfully imported %d price history entries for product "%s"', 'wc-ph-import' ),
				$imported_count,
				$target_product->get_name()
			),
			'imported_count' => $imported_count,
		];
	}

	/**
	 * Find matching variation by attributes.
	 *
	 * @param \WC_Product $product           Target product.
	 * @param array       $source_attributes Attributes from source variation.
	 *
	 * @return int|false
	 */
	private function find_matching_variation( \WC_Product $product, array $source_attributes ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return false;
		}

		$variations = $product->get_available_variations( 'objects' );

		foreach ( $variations as $variation ) {
			$variation_attributes = $variation->get_attributes();
			$match                = true;

			foreach ( $source_attributes as $attr_name => $attr_value ) {
				if ( isset( $variation_attributes[ $attr_name ] ) ) {
					$variation_value = $variation_attributes[ $attr_name ];

					if ( is_array( $attr_value ) ) {
						continue;
					}

					if ( $variation_value !== $attr_value ) {
						$match = false;
						break;
					}
				}
			}

			if ( $match ) {
				return $variation->get_id();
			}
		}

		return false;
	}


	/**
	 * Get admin CSS.
	 */
	private function get_admin_css(): string {
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


