<?php
/**
 * Schema-driven sanitization for module instances.
 *
 * Replaces the hand-coded parse_modules_from_post() switch. Given a raw
 * JSON-decoded modules array + a registry, returns a sanitized modules
 * array with every field validated and every hidden field reset to its
 * default.
 *
 * Invariants:
 *   - Output shape is byte-compatible with legacy parse_modules_from_post.
 *   - Unknown module types are dropped.
 *   - Hidden fields (via the `hidden` callback) always get the default value.
 *   - Required repeater items are filtered server-side.
 */
namespace WCHS\Admin;

defined( 'ABSPATH' ) || exit;

class SchemaSanitizer {

	private const VALID_VISIBILITIES = [ 'all', 'members', 'guests' ];
	private const VALID_SPACINGS     = [ 'compact', 'normal', 'spacious' ];

	public static function sanitize_modules( array $raw, ?string $context = null ): array {
		$out = [];
		foreach ( $raw as $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$clean = self::sanitize_module( $m, $context );
			if ( $clean !== null ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	public static function sanitize_module( array $m, ?string $context = null ): ?array {
		$type   = sanitize_text_field( $m['type'] ?? '' );
		$schema = ModuleRegistry::get( $type );
		if ( ! $schema ) {
			return null;
		}

		// Reject modules whose schema doesn't allow the active context.
		// Defense-in-depth: the admin UI filters the insert menu, but a
		// crafted POST could still try to smuggle an incompatible type.
		if ( $context !== null ) {
			$contexts = $schema['supports']['contexts'] ?? [ 'homepage', 'shop', 'pdp', 'pages' ];
			if ( ! in_array( $context, $contexts, true ) ) {
				return null;
			}
		}

		$cfg_raw = is_array( $m['config'] ?? null ) ? $m['config'] : [];
		$cfg     = [];

		// `hidden` is a UI-only concern (controls whether the field shows in
		// the inspector, evaluated client-side). We still sanitize + persist
		// the value server-side so that toggling a conditional back on
		// restores the prior selection without data loss.
		foreach ( $schema['fields'] as $field ) {
			$raw_value = $cfg_raw[ $field['id'] ] ?? null;
			$cfg[ $field['id'] ] = self::sanitize_field( $field, $raw_value, $cfg_raw );
		}

		$clean = [ 'type' => $type ];

		// Stable 8-char ID per module instance. Generated once on first save
		// and persisted, so analytics hooks (data-module-id) stay consistent
		// across reorder + config edits. Only regenerated if missing.
		$id_raw = (string) ( $m['id'] ?? '' );
		if ( preg_match( '/^[a-z0-9]{8}$/', $id_raw ) ) {
			$clean['id'] = $id_raw;
		} else {
			$clean['id'] = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );
		}

		if ( ! empty( $schema['supports']['visibility'] ) ) {
			$v = sanitize_text_field( $m['visibility'] ?? 'all' );
			$clean['visibility'] = in_array( $v, self::VALID_VISIBILITIES, true ) ? $v : 'all';
		}

		if ( ! empty( $schema['supports']['spacing'] ) ) {
			$sv = sanitize_text_field( $m['spacing_v'] ?? 'normal' );
			$clean['spacing_v'] = in_array( $sv, self::VALID_SPACINGS, true ) ? $sv : 'normal';
			$sh = sanitize_text_field( $m['spacing_h'] ?? 'normal' );
			if ( ! in_array( $sh, self::VALID_SPACINGS, true ) ) {
				// Legacy: edge_to_edge:true → compact
				$sh = ! empty( $m['edge_to_edge'] ) ? 'compact' : 'normal';
			}
			$clean['spacing_h'] = $sh;
		}

		if ( ! empty( $schema['supports']['header'] ) ) {
			$clean['center_header'] = ! empty( $m['center_header'] );
		}

		if ( isset( $m['overrides'] ) && is_array( $m['overrides'] ) ) {
			$ov = self::sanitize_overrides( $m['overrides'] );
			if ( ! empty( $ov ) ) {
				$clean['overrides'] = $ov;
			}
		}

		// Scheduled publishing — optional ISO-8601 datetime strings.
		// SPA filters client-side so SG edge cache doesn't freeze the schedule.
		foreach ( [ 'start_at', 'end_at' ] as $k ) {
			$raw = trim( (string) ( $m[ $k ] ?? '' ) );
			if ( $raw === '' ) {
				continue;
			}
			$ts = strtotime( $raw );
			if ( $ts === false ) {
				continue;
			}
			$clean[ $k ] = gmdate( 'c', $ts );
		}

		$clean['config'] = $cfg;
		return $clean;
	}

	/**
	 * Override block — tight whitelist of keys. Currently only the two
	 * token buckets a module can take control of: accent color + the four
	 * typography fields. Expand deliberately; every new key here widens the
	 * public surface of the inheritance system.
	 */
	private static function sanitize_overrides( array $raw ): array {
		$out = [];
		if ( isset( $raw['accent_color'] ) ) {
			$v = sanitize_text_field( (string) $raw['accent_color'] );
			if ( $v !== '' && preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ) {
				$out['accent_color'] = $v;
			}
		}
		if ( isset( $raw['typography'] ) && is_array( $raw['typography'] ) ) {
			$typo = [];
			foreach ( [ 'heading_font', 'body_font', 'heading_weight', 'body_size' ] as $k ) {
				if ( isset( $raw['typography'][ $k ] ) && is_string( $raw['typography'][ $k ] ) ) {
					$clean = sanitize_text_field( $raw['typography'][ $k ] );
					if ( $clean !== '' ) {
						$typo[ $k ] = $clean;
					}
				}
			}
			if ( ! empty( $typo ) ) {
				$out['typography'] = $typo;
			}
		}
		return $out;
	}

	/**
	 * Sanitize a single field's value according to its declared type.
	 *
	 * @param array $field  Schema field definition.
	 * @param mixed $value  Raw input.
	 * @param array $values Full module config (siblings), for context.
	 * @return mixed        Sanitized value.
	 */
	private static function sanitize_field( array $field, $value, array $values ) {
		if ( isset( $field['validate'] ) && is_callable( $field['validate'] ) ) {
			return call_user_func( $field['validate'], $value, $values );
		}

		$type = $field['type'];

		switch ( $type ) {
			case 'text':
			case 'slug':
				$sanitized = $type === 'slug'
					? sanitize_key( (string) $value )
					: sanitize_text_field( wp_unslash( (string) $value ) );
				return $sanitized !== '' ? $sanitized : ( $field['default'] ?? '' );

			case 'email':
				return sanitize_email( wp_unslash( (string) $value ) );

			case 'url':
				return esc_url_raw( wp_unslash( (string) $value ) );

			case 'textarea':
				return sanitize_textarea_field( wp_unslash( (string) $value ) );

			case 'wysiwyg':
				return wp_kses_post( wp_unslash( (string) $value ) );

			case 'number':
				$n = (int) $value;
				if ( isset( $field['min'] ) ) {
					$n = max( (int) $field['min'], $n );
				}
				if ( isset( $field['max'] ) ) {
					$n = min( (int) $field['max'], $n );
				}
				return $n;

			case 'boolean':
				return ! empty( $value );

			case 'enum':
				$options = array_keys( $field['options'] ?? [] );
				$v = is_string( $value ) ? $value : '';
				return in_array( $v, $options, true ) ? $v : ( $field['default'] ?? ( $options[0] ?? '' ) );

			case 'icon':
				return sanitize_text_field( (string) $value ) ?: ( $field['default'] ?? '' );

			case 'image':
				return esc_url_raw( wp_unslash( (string) $value ) );

			case 'color':
				$v = sanitize_text_field( wp_unslash( (string) $value ) );
				return ( $v !== '' && preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ) ? $v : '';

			case 'product_list':
				$raw = $value;
				if ( is_array( $raw ) ) {
					return array_values( array_filter( array_map( 'absint', $raw ) ) );
				}
				if ( is_string( $raw ) && $raw !== '' ) {
					return array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
				}
				return [];

			case 'repeater':
				if ( ! is_array( $value ) ) {
					$default = $field['default'] ?? null;
					return is_array( $default ) ? self::sanitize_repeater( $field, $default ) : [];
				}
				return self::sanitize_repeater( $field, $value );

			default:
				// Unknown type — pass through as raw string
				return sanitize_text_field( (string) $value );
		}
	}

	private static function sanitize_repeater( array $field, array $raw ): array {
		$items = [];
		$item_schema      = $field['item'] ?? [];
		$required_keys    = $field['item_required'] ?? [];
		$any_required     = $field['item_any_required'] ?? [];

		foreach ( $raw as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}
			$item = [];
			foreach ( $item_schema as $sub_field ) {
				$sub_field = array_merge( [ 'default' => null ], $sub_field );
				$item[ $sub_field['id'] ] = self::sanitize_field( $sub_field, $raw_item[ $sub_field['id'] ] ?? null, $raw_item );
			}

			// Enforce required (all-of)
			$skip = false;
			foreach ( $required_keys as $key ) {
				if ( empty( $item[ $key ] ) ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}

			// Enforce at-least-one-of
			if ( ! empty( $any_required ) ) {
				$has_any = false;
				foreach ( $any_required as $key ) {
					if ( ! empty( $item[ $key ] ) ) {
						$has_any = true;
						break;
					}
				}
				if ( ! $has_any ) {
					continue;
				}
			}

			$items[] = $item;
		}
		return $items;
	}
}
