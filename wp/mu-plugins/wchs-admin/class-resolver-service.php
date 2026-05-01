<?php
/**
 * Resolver service — merges site defaults + per-module overrides into a
 * `resolved` block on each module. Lets SPA components consume a single
 * settled value without re-implementing the cascade.
 *
 * Cascade order (lowest → highest precedence):
 *   1. site defaults (wchs_site_settings)
 *   2. per-module overrides (module.overrides)
 *
 * Per-page overrides are a planned future layer — the function signature
 * takes an optional $page_overrides arg so we can slot it in without
 * touching call sites.
 */
namespace WCHS\Admin;

defined( 'ABSPATH' ) || exit;

class ResolverService {

	/**
	 * Annotate each module in $modules with a `resolved` block + an
	 * `inherited` map of which tokens came from defaults vs override.
	 *
	 * @param array $modules       List of sanitized modules.
	 * @param array $site_settings wchs_site_settings option value.
	 * @param array $page_overrides Future: per-page overrides layer.
	 * @return array Same list, with each element gaining `resolved` + `inherited`.
	 */
	public static function resolve_modules( array $modules, array $site_settings, array $page_overrides = [] ): array {
		$defaults = self::site_defaults( $site_settings );

		return array_map(
			function ( $m ) use ( $defaults, $page_overrides ) {
				if ( ! is_array( $m ) ) {
					return $m;
				}
				$overrides = is_array( $m['overrides'] ?? null ) ? $m['overrides'] : [];

				$resolved = self::deep_merge( $defaults, $page_overrides, $overrides );
				$source   = self::source_map( $defaults, $page_overrides, $overrides );

				$m['resolved']  = $resolved;
				$m['inherited'] = $source;
				return $m;
			},
			$modules
		);
	}

	/**
	 * Canonical shape of the defaults block. Only keys that are eligible
	 * for per-module override live here — keep it tight so the override
	 * surface doesn't accidentally grow.
	 */
	private static function site_defaults( array $site_settings ): array {
		return [
			'accent_color' => is_string( $site_settings['accent_color'] ?? null ) && $site_settings['accent_color'] !== ''
				? $site_settings['accent_color'] : null,
			'typography' => [
				'heading_font'   => $site_settings['typography_heading_font']   ?? 'inter',
				'heading_weight' => $site_settings['typography_heading_weight'] ?? 'semibold',
				'body_font'      => $site_settings['typography_body_font']      ?? 'inter',
				'body_size'      => $site_settings['typography_body_size']      ?? 'm',
			],
		];
	}

	/**
	 * Recursive array merge — later args take precedence over earlier.
	 * Scalars replace; arrays merge key-by-key. Nulls are treated as
	 * "no override" (don't clobber a resolved value with null).
	 */
	private static function deep_merge( array ...$layers ): array {
		$out = [];
		foreach ( $layers as $layer ) {
			foreach ( $layer as $key => $value ) {
				if ( $value === null ) {
					continue;
				}
				if ( is_array( $value ) && isset( $out[ $key ] ) && is_array( $out[ $key ] ) ) {
					$out[ $key ] = self::deep_merge( $out[ $key ], $value );
				} else {
					$out[ $key ] = $value;
				}
			}
		}
		return $out;
	}

	/**
	 * Map each resolved leaf to its source layer ("default" | "page" | "module").
	 * Useful for the inspector to show "inherited from Design" vs "overridden".
	 */
	private static function source_map( array $defaults, array $page_overrides, array $module_overrides ): array {
		$out = [];
		self::walk_source( $defaults, 'default', $out, '' );
		self::walk_source( $page_overrides, 'page', $out, '' );
		self::walk_source( $module_overrides, 'module', $out, '' );
		return $out;
	}

	private static function walk_source( array $layer, string $source, array &$out, string $prefix ): void {
		foreach ( $layer as $key => $value ) {
			$path = $prefix === '' ? $key : $prefix . '.' . $key;
			if ( is_array( $value ) ) {
				self::walk_source( $value, $source, $out, $path );
			} elseif ( $value !== null ) {
				$out[ $path ] = $source;
			}
		}
	}
}
