<?php
/**
 * AJAX search endpoints for products and categories.
 * Powers the Select2 searchable dropdowns in the admin builder.
 *
 * @package MatrixBogo\Admin
 */

declare( strict_types=1 );

namespace MatrixBogo\Admin;

use MatrixBogo\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AjaxSearch
 *
 * Provides four nopriv-free AJAX actions:
 *  - matrix_bogo_search_products      : search by name / SKU / ID
 *  - matrix_bogo_search_categories    : search by name / ID
 *  - matrix_bogo_get_product_details  : resolve saved product IDs to labels
 *  - matrix_bogo_get_category_details : resolve saved category IDs to labels
 */
final class AjaxSearch {

	public function init( Loader $loader ): void {
		$loader->add_action( 'wp_ajax_matrix_bogo_search_products',      [ $this, 'search_products' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_search_categories',    [ $this, 'search_categories' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_get_product_details',  [ $this, 'get_product_details' ] );
		$loader->add_action( 'wp_ajax_matrix_bogo_get_category_details', [ $this, 'get_category_details' ] );
	}

	// -----------------------------------------------------------------
	// Product search
	// -----------------------------------------------------------------

	public function search_products(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$q    = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		$results = [];
		$seen    = [];

		// 1. Direct numeric ID lookup.
		if ( is_numeric( $q ) && (int) $q > 0 ) {
			$product = wc_get_product( absint( $q ) );
			if ( $product && 'publish' === $product->get_status() ) {
				$results[]          = $this->format_product( $product );
				$seen[ $product->get_id() ] = true;
			}
		}

		// 2. Search by product title.
		$by_name = wc_get_products(
			[
				's'      => $q,
				'limit'  => 20,
				'status' => 'publish',
				'return' => 'objects',
			]
		);
		foreach ( $by_name as $p ) {
			if ( ! isset( $seen[ $p->get_id() ] ) ) {
				$results[]             = $this->format_product( $p );
				$seen[ $p->get_id() ] = true;
			}
		}

		// 3. Search by SKU (partial LIKE match).
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$sku_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id
				   FROM {$wpdb->postmeta}
				  WHERE meta_key = '_sku'
				    AND meta_value LIKE %s
				  LIMIT 10",
				$like
			)
		);
		foreach ( $sku_ids as $pid ) {
			$pid = absint( $pid );
			if ( ! isset( $seen[ $pid ] ) ) {
				$product = wc_get_product( $pid );
				if ( $product && 'publish' === $product->get_status() ) {
					$results[]      = $this->format_product( $product );
					$seen[ $pid ]   = true;
				}
			}
		}

		wp_send_json_success( [ 'results' => array_slice( $results, 0, 20 ) ] );
	}

	/**
	 * Resolve a comma-separated list of product IDs into labelled options.
	 * Called on builder load to pre-populate saved select values.
	 */
	public function get_product_details(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$raw = sanitize_text_field( wp_unslash( $_POST['ids'] ?? '' ) );
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

		$results = [];
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$results[] = $this->format_product( $product );
			}
		}

		wp_send_json_success( [ 'results' => $results ] );
	}

	/**
	 * Format a WC_Product into a Select2 {id, text} pair.
	 */
	private function format_product( \WC_Product $product ): array {
		$sku  = $product->get_sku();
		$text = $product->get_name();
		if ( $sku ) {
			$text .= ' [' . $sku . ']';
		}
		$text .= ' #' . $product->get_id();
		return [ 'id' => $product->get_id(), 'text' => $text ];
	}

	// -----------------------------------------------------------------
	// Category search
	// -----------------------------------------------------------------

	public function search_categories(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$q    = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		$results = [];
		$seen    = [];

		// 1. Direct numeric ID lookup.
		if ( is_numeric( $q ) && (int) $q > 0 ) {
			$term = get_term( absint( $q ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$results[]                  = $this->format_category( $term );
				$seen[ $term->term_id ]     = true;
			}
		}

		// 2. Search by name.
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'search'     => $q,
				'number'     => 20,
				'hide_empty' => false,
			]
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! isset( $seen[ $term->term_id ] ) ) {
					$results[]                  = $this->format_category( $term );
					$seen[ $term->term_id ]     = true;
				}
			}
		}

		wp_send_json_success( [ 'results' => array_slice( $results, 0, 20 ) ] );
	}

	/**
	 * Resolve a comma-separated list of category IDs into labelled options.
	 */
	public function get_category_details(): void {
		check_ajax_referer( 'matrix_bogo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'matrix-bogo' ) ] );
		}

		$raw = sanitize_text_field( wp_unslash( $_POST['ids'] ?? '' ) );
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

		$results = [];
		foreach ( $ids as $id ) {
			$term = get_term( $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$results[] = $this->format_category( $term );
			}
		}

		wp_send_json_success( [ 'results' => $results ] );
	}

	/**
	 * Format a WP_Term (product_cat) into a Select2 {id, text} pair.
	 */
	private function format_category( \WP_Term $term ): array {
		return [
			'id'   => $term->term_id,
			'text' => $term->name . ' #' . $term->term_id,
		];
	}
}
