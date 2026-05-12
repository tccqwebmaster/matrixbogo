<?php
/**
 * Sanitizer – sanitization helpers for rule/condition/reward data.
 *
 * @package MatrixBogo\Helpers
 */

declare( strict_types=1 );

namespace MatrixBogo\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sanitizer
 */
final class Sanitizer {

	/**
	 * Recursively sanitize an array of text fields.
	 */
	public static function sanitize_text_array( array $data ): array {
		return array_map(
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return self::sanitize_text_array( $value );
				}
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
			},
			$data
		);
	}

	/**
	 * Sanitize a rule_data JSON blob.
	 */
	public static function sanitize_rule_data( array $data ): string {
		$clean = self::sanitize_text_array( $data );
		return wp_json_encode( $clean ) ?: '{}';
	}

	/**
	 * Ensure an array of IDs contains only positive integers.
	 */
	public static function sanitize_id_array( array $ids ): array {
		return array_values( array_filter( array_map( 'intval', $ids ), static fn( $id ) => $id > 0 ) );
	}

	/**
	 * Sanitize a price/float value.
	 */
	public static function sanitize_price( mixed $value ): float {
		return max( 0.0, (float) $value );
	}
}
