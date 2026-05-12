<?php
/**
 * License Manager – validates and stores the plugin license key.
 *
 * @package MatrixBogo\License
 */

declare( strict_types=1 );

namespace MatrixBogo\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LicenseManager
 *
 * In a real commercial plugin this would communicate with a licensing server
 * (e.g., Freemius, WooCommerce.com, or a custom EDD-SL endpoint).
 * This implementation stores the key locally and provides the API surface
 * expected by the rest of the plugin.
 */
final class LicenseManager {

	private const OPTION_KEY    = 'matrix_bogo_license_key';
	private const OPTION_STATUS = 'matrix_bogo_license_status';

	// Possible statuses.
	public const STATUS_VALID    = 'valid';
	public const STATUS_INVALID  = 'invalid';
	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_EXPIRED  = 'expired';

	// -----------------------------------------------------------------
	// Getters
	// -----------------------------------------------------------------

	public function get_license_key(): string {
		return (string) get_option( self::OPTION_KEY, '' );
	}

	public function get_status(): string {
		return (string) get_option( self::OPTION_STATUS, self::STATUS_INACTIVE );
	}

	public function is_valid(): bool {
		return self::STATUS_VALID === $this->get_status();
	}

	public function get_status_label(): string {
		return match ( $this->get_status() ) {
			self::STATUS_VALID    => __( 'Active', 'matrix-bogo' ),
			self::STATUS_INVALID  => __( 'Invalid', 'matrix-bogo' ),
			self::STATUS_EXPIRED  => __( 'Expired', 'matrix-bogo' ),
			default               => __( 'Not Activated', 'matrix-bogo' ),
		};
	}

	// -----------------------------------------------------------------
	// Activation
	// -----------------------------------------------------------------

	/**
	 * Attempt to activate the given license key.
	 *
	 * Extend this method to call your licensing API.
	 *
	 * @return true|\WP_Error
	 */
	public function activate( string $key ): \WP_Error|bool {
		$key = sanitize_text_field( $key );

		if ( empty( $key ) ) {
			return new \WP_Error( 'empty_key', __( 'Please enter a license key.', 'matrix-bogo' ) );
		}

		/**
		 * Replace the block below with a real API call, e.g.:
		 *
		 * $response = wp_remote_post( 'https://matrixplugins.com/api/license/activate', [
		 *     'body' => [ 'license_key' => $key, 'site_url' => home_url() ],
		 *     'timeout' => 15,
		 * ] );
		 *
		 * if ( is_wp_error( $response ) ) { return $response; }
		 * $body = json_decode( wp_remote_retrieve_body( $response ), true );
		 * if ( empty( $body['valid'] ) ) {
		 *     return new \WP_Error( 'invalid', $body['message'] ?? __( 'Invalid license key.', 'matrix-bogo' ) );
		 * }
		 */

		// Stub: always mark valid for development builds.
		update_option( self::OPTION_KEY, $key );
		update_option( self::OPTION_STATUS, self::STATUS_VALID );

		return true;
	}

	/**
	 * Deactivate the current license.
	 */
	public function deactivate(): void {
		update_option( self::OPTION_STATUS, self::STATUS_INACTIVE );
	}
}
