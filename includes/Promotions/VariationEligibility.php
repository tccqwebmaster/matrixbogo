<?php
/**
 * Variation eligibility – which gift promotions an individual product
 * option (variation) was ticked for in the product editor, plus WPML ID
 * mapping for option-level rules.
 *
 * @package MatrixBogo\Promotions
 */

declare( strict_types=1 );

namespace MatrixBogo\Promotions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VariationEligibility
 */
final class VariationEligibility {

	/** Variation meta: int[] of promotion (rule) IDs this option is included in. */
	public const META_KEY = '_matrix_bogo_promo_ids';

	/** @var array<int, int[]> Per-request cache keyed by variation ID. */
	private static array $cache = [];

	/**
	 * Promotion IDs ticked on a variation. Under WPML the default-language
	 * original's setting counts too, so an Arabic option behaves exactly like
	 * its English one even when WCML has not copied the meta yet.
	 *
	 * @param int $vid Variation ID.
	 * @return int[]
	 */
	public static function rule_ids_for( int $vid ): array {
		if ( $vid <= 0 ) {
			return [];
		}
		if ( isset( self::$cache[ $vid ] ) ) {
			return self::$cache[ $vid ];
		}

		$ids  = self::read( $vid );
		$orig = self::original_id( $vid, 'product_variation' );
		if ( $orig > 0 && $orig !== $vid ) {
			$ids = array_merge( $ids, self::read( $orig ) );
		}

		return self::$cache[ $vid ] = array_values( array_unique( $ids ) );
	}

	/**
	 * Saves the promotion IDs for a variation (empty array deletes the meta).
	 *
	 * @param int   $vid
	 * @param int[] $rule_ids
	 */
	public static function save( int $vid, array $rule_ids ): void {
		$rule_ids = array_values( array_unique( array_filter( array_map( 'absint', $rule_ids ) ) ) );
		if ( empty( $rule_ids ) ) {
			delete_post_meta( $vid, self::META_KEY );
		} else {
			update_post_meta( $vid, self::META_KEY, $rule_ids );
		}
		unset( self::$cache[ $vid ] );
	}

	/**
	 * Adds every WPML translation of the given post / term IDs (WPML gives
	 * each language its own ID). No-op without WPML.
	 *
	 * @param int[]  $ids
	 * @param string $type WPML element type: 'product_variation', 'product', 'product_cat', 'product_tag'.
	 * @return int[]
	 */
	public static function with_translations( array $ids, string $type ): array {
		if ( empty( $ids ) || ! has_filter( 'wpml_object_id' ) ) {
			return $ids;
		}
		$languages = (array) apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
		$out       = $ids;
		foreach ( $ids as $id ) {
			foreach ( array_keys( $languages ) as $lang ) {
				$tid = (int) apply_filters( 'wpml_object_id', $id, $type, false, $lang );
				if ( $tid > 0 ) {
					$out[] = $tid;
				}
			}
		}
		return array_values( array_unique( array_map( 'intval', $out ) ) );
	}

	/**
	 * Default-language original of a post under WPML; 0 without WPML.
	 */
	public static function original_id( int $id, string $type ): int {
		if ( ! has_filter( 'wpml_object_id' ) ) {
			return 0;
		}
		$default = (string) apply_filters( 'wpml_default_language', null );
		return (int) apply_filters( 'wpml_object_id', $id, $type, false, $default );
	}

	/**
	 * @return int[]
	 */
	private static function read( int $vid ): array {
		$raw = get_post_meta( $vid, self::META_KEY, true );
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		return array_values( array_filter( array_map( 'intval', (array) $raw ) ) );
	}
}
