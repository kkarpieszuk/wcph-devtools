<?php

namespace WcPriceHistory\WcphDevtools;

use PriorPrice\HistoryStorage;

/**
 * AllHistoryExporter class.
 *
 * Handles export of all products' price history.
 */
class AllHistoryExporter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wc_ph_get_all_products_ids', [ $this, 'handle_get_all_products_ids' ] );
		add_action( 'wp_ajax_wc_ph_get_products_price_details', [ $this, 'handle_get_products_price_details' ] );
		add_action( 'wp_ajax_wc_ph_export_csv', [ $this, 'handle_export_csv' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wc_ph_devtools_admin_page_after_import', [ $this, 'render_section' ] );
	}

	/**
	 * Enqueue admin scripts.
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
			'wc-ph-devtools-all-history-exporter',
			$plugin_url . 'assets/js/all-history-exporter.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);
	}

	/**
	 * Render export section on admin page.
	 */
	public function render_section(): void {
		wp_nonce_field( 'wc_ph_export_nonce', 'wc_ph_export_nonce' );
		?>
		<div class="card">
			<h2><?php esc_html_e( "Export All Products' History", 'wc-ph-devtools' ); ?></h2>

			<p class="submit">
				<button type="button" class="button-primary" id="wc-ph-export-all-button">
					<?php esc_html_e( 'Start Exporting', 'wc-ph-devtools' ); ?>
				</button>
			</p>

			<div id="export-result" style="display: none;"></div>
		</div>
		<?php
	}

	/**
	 * Get all products IDs including variations.
	 *
	 * @return array<int> Array of all product and variation IDs.
	 */
	public function get_all_products_ids(): array {
		global $wpdb;

		$all_ids = [];

		// Get all products using direct SQL query (similar to DbMigration approach)
		// This ensures we get ALL products, not just published ones
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				WHERE p.post_type = %s
				AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')",
				'product'
			)
		);

		foreach ( $product_ids as $product_id ) {
			$all_ids[] = (int) $product_id;
		}

		// Get all product variations using direct SQL query
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$variation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				WHERE p.post_type = %s
				AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')",
				'product_variation'
			)
		);

		foreach ( $variation_ids as $variation_id ) {
			$all_ids[] = (int) $variation_id;
		}

		return array_unique( $all_ids );
	}

	/**
	 * Handle AJAX request to get all products IDs.
	 */
	public function handle_get_all_products_ids(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wc_ph_export_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'wc-ph-devtools' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to export data', 'wc-ph-devtools' ) ] );
		}

		$product_ids = $this->get_all_products_ids();

		wp_send_json_success(
			[
				'product_ids' => $product_ids,
				'count'       => count( $product_ids ),
			]
		);
	}

	/**
	 * Get products price details.
	 *
	 * @param array<int> $product_ids Array of product IDs.
	 *
	 * @return array<array<string, mixed>> Array of product details.
	 */
	public function getProductsPriceDetails( array $product_ids ): array {
		$history_storage = new HistoryStorage();
		$products_data   = [];

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$current_price = (float) $product->get_price();
			$lowest_price  = (float) $history_storage->get_minimal( $product_id, 30 );

			$products_data[] = [
				'id'          => $product_id,
				'title'       => $product->get_name(),
				'permalink'   => get_permalink( $product_id ),
				'current_price' => $current_price,
				'lowest_price'   => $lowest_price,
			];
		}

		return $products_data;
	}

	/**
	 * Handle AJAX request to get products price details.
	 */
	public function handle_get_products_price_details(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wc_ph_export_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'wc-ph-devtools' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to export data', 'wc-ph-devtools' ) ] );
		}

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', $_POST['product_ids'] ) : [];

		if ( empty( $product_ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No product IDs provided', 'wc-ph-devtools' ) ] );
		}

		$products_data = $this->getProductsPriceDetails( $product_ids );

		wp_send_json_success(
			[
				'products' => $products_data,
			]
		);
	}

	/**
	 * Generate CSV from products data.
	 *
	 * @param array<array<string, mixed>> $products_data Products data.
	 *
	 * @return string CSV content.
	 */
	private function generate_csv( array $products_data ): string {
		$output = fopen( 'php://temp', 'r+' );

		// Add BOM for UTF-8
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Add headers
		fputcsv(
			$output,
			[
				'ID',
				'Title',
				'Permalink',
				'Current Price',
				'Lowest Price',
			]
		);

		// Add data rows
		foreach ( $products_data as $product ) {
			fputcsv(
				$output,
				[
					$product['id'],
					$product['title'],
					$product['permalink'],
					$product['current_price'],
					$product['lowest_price'],
				]
			);
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Handle AJAX request to export CSV.
	 */
	public function handle_export_csv(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wc_ph_export_nonce' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'wc-ph-devtools' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export data', 'wc-ph-devtools' ) );
		}

		$products_data = isset( $_POST['products_data'] ) ? json_decode( wp_unslash( $_POST['products_data'] ), true ) : [];

		if ( empty( $products_data ) || ! is_array( $products_data ) ) {
			wp_die( esc_html__( 'No products data provided', 'wc-ph-devtools' ) );
		}

		$csv_content = $this->generate_csv( $products_data );
		$filename    = 'wc-price-history-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

		// Set headers for CSV download
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $csv_content ) );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );

		// Output CSV
		echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

}

