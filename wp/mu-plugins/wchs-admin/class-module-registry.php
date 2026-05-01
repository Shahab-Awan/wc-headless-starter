<?php
/**
 * Module registry — single source of truth for every module type.
 *
 * Loads per-module schema files from ./modules/*.php. Each file returns an
 * associative array describing the module (type, supports, fields).
 *
 * Extension point: `wchs_module_registry` filter lets plugins append or
 * override schemas without touching core files.
 */
namespace WCHS\Admin;

defined( 'ABSPATH' ) || exit;

class ModuleRegistry {

	/** @var array<string, array>|null memoized registry */
	private static ?array $cache = null;

	/**
	 * Return all registered module schemas keyed by type.
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		if ( self::$cache !== null ) {
			return self::$cache;
		}
		$schemas = [];
		$dir = __DIR__ . '/modules/';
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$schema = include $file;
			if ( ! is_array( $schema ) || empty( $schema['type'] ) ) {
				continue;
			}
			$schemas[ $schema['type'] ] = self::normalize( $schema );
		}
		/**
		 * Filter the module schema registry.
		 *
		 * @param array<string, array> $schemas Keyed by module type.
		 */
		$schemas = apply_filters( 'wchs_module_registry', $schemas );
		self::$cache = $schemas;
		return $schemas;
	}

	public static function get( string $type ): ?array {
		$all = self::all();
		return $all[ $type ] ?? null;
	}

	public static function types(): array {
		return array_keys( self::all() );
	}

	/**
	 * Force a reload from disk. Called only in tests.
	 */
	public static function reset_cache(): void {
		self::$cache = null;
	}

	private static function normalize( array $schema ): array {
		$schema['supports'] = array_merge(
			[
				'spacing'    => true,
				'visibility' => true,
				'header'     => true,
				// Default: insertable anywhere the builder runs. Module schemas
				// narrow this (e.g. shop_grid only on 'shop', contact_form only
				// on 'pages') so the insert menu can filter by context.
				'contexts'   => [ 'homepage', 'shop', 'pdp', 'pages' ],
			],
			$schema['supports'] ?? []
		);
		$schema['fields'] = array_map(
			function ( $field ) {
				return array_merge(
					[
						'default' => null,
						'hidden'  => false,
					],
					$field
				);
			},
			$schema['fields'] ?? []
		);
		return $schema;
	}
}
