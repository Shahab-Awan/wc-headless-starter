<?php
/**
 * Plugin Name: WCHS Origin Config
 * Description: Centralized origin resolution for same-origin and custom-origin WCHS deployments.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wchs_normalize_origin' ) ) {
	function wchs_normalize_origin( $origin ): string {
		if ( ! is_string( $origin ) ) {
			return '';
		}

		$origin = trim( $origin );
		if ( $origin === '' ) {
			return '';
		}

		$parts = wp_parse_url( $origin );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return '';
		}

		$host   = strtolower( (string) $parts['host'] );
		$result = $scheme . '://' . $host;
		if ( isset( $parts['port'] ) ) {
			$result .= ':' . (int) $parts['port'];
		}

		return untrailingslashit( $result );
	}
}

if ( ! function_exists( 'wchs_parse_origin_list' ) ) {
	function wchs_parse_origin_list( $value ): array {
		$items = [];
		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_string( $value ) ) {
			$items = preg_split( '/[\r\n,]+/', $value ) ?: [];
		}

		$out = [];
		foreach ( $items as $item ) {
			$origin = wchs_normalize_origin( $item );
			if ( $origin !== '' ) {
				$out[] = $origin;
			}
		}

		return array_values( array_unique( $out ) );
	}
}

if ( ! function_exists( 'wchs_origin_settings_raw' ) ) {
	function wchs_origin_settings_raw(): array {
		$saved = get_option( 'wchs_site_settings', [] );
		return is_array( $saved ) ? $saved : [];
	}
}

if ( ! function_exists( 'wchs_is_local_host' ) ) {
	function wchs_is_local_host( string $host ): bool {
		$host = strtolower( $host );
		if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
			return true;
		}

		return (bool) preg_match( '/(\.test|\.local|\.localhost|\.invalid)$/', $host );
	}
}

if ( ! function_exists( 'wchs_is_local_origin' ) ) {
	function wchs_is_local_origin( string $origin ): bool {
		$host = wp_parse_url( $origin, PHP_URL_HOST );
		return is_string( $host ) && $host !== '' && wchs_is_local_host( $host );
	}
}

if ( ! function_exists( 'wchs_public_origin' ) ) {
	function wchs_public_origin(): string {
		$origin = wchs_normalize_origin( home_url( '/' ) );
		if ( $origin !== '' ) {
			return $origin;
		}

		$origin = wchs_normalize_origin( get_option( 'home', '' ) );
		if ( $origin !== '' ) {
			return $origin;
		}

		return 'http://localhost';
	}
}

if ( ! function_exists( 'wchs_legacy_origin_constants' ) ) {
	function wchs_legacy_origin_constants(): array {
		return [
			'spa_origin'      => defined( 'WCHS_SPA_URL' ) && is_string( WCHS_SPA_URL ) ? wchs_normalize_origin( WCHS_SPA_URL ) : '',
			'allowed_origins' => defined( 'WCHS_ALLOWED_ORIGINS' ) && is_string( WCHS_ALLOWED_ORIGINS ) ? wchs_parse_origin_list( WCHS_ALLOWED_ORIGINS ) : [],
			'return_origins'  => defined( 'WCHS_RETURN_ORIGINS' ) && is_string( WCHS_RETURN_ORIGINS ) ? wchs_parse_origin_list( WCHS_RETURN_ORIGINS ) : [],
		];
	}
}

if ( ! function_exists( 'wchs_origin_mode' ) ) {
	function wchs_origin_mode(): string {
		$settings = wchs_origin_settings_raw();
		$mode     = sanitize_key( (string) ( $settings['domain_origin_mode'] ?? '' ) );
		if ( in_array( $mode, [ 'same-origin', 'custom' ], true ) ) {
			return $mode;
		}

		$legacy = wchs_legacy_origin_constants();
		$has_legacy = $legacy['spa_origin'] !== '' || ! empty( $legacy['allowed_origins'] ) || ! empty( $legacy['return_origins'] );
		if ( $has_legacy && wchs_is_local_origin( wchs_public_origin() ) ) {
			return 'custom';
		}

		return 'same-origin';
	}
}

if ( ! function_exists( 'wchs_origin_mode_source' ) ) {
	function wchs_origin_mode_source(): string {
		$settings = wchs_origin_settings_raw();
		$mode     = sanitize_key( (string) ( $settings['domain_origin_mode'] ?? '' ) );
		if ( in_array( $mode, [ 'same-origin', 'custom' ], true ) ) {
			return 'setting';
		}

		$legacy = wchs_legacy_origin_constants();
		$has_legacy = $legacy['spa_origin'] !== '' || ! empty( $legacy['allowed_origins'] ) || ! empty( $legacy['return_origins'] );
		if ( $has_legacy && wchs_is_local_origin( wchs_public_origin() ) ) {
			return 'legacy-local-dev';
		}

		return 'default';
	}
}

if ( ! function_exists( 'wchs_custom_spa_origin' ) ) {
	function wchs_custom_spa_origin(): string {
		$settings = wchs_origin_settings_raw();
		$custom   = wchs_normalize_origin( $settings['custom_spa_origin'] ?? '' );
		if ( $custom !== '' ) {
			return $custom;
		}

		return wchs_legacy_origin_constants()['spa_origin'];
	}
}

if ( ! function_exists( 'wchs_custom_allowed_origins' ) ) {
	function wchs_custom_allowed_origins(): array {
		$settings = wchs_origin_settings_raw();
		$custom   = wchs_parse_origin_list( $settings['custom_allowed_origins'] ?? [] );
		if ( ! empty( $custom ) ) {
			return $custom;
		}

		return wchs_legacy_origin_constants()['allowed_origins'];
	}
}

if ( ! function_exists( 'wchs_custom_return_origins' ) ) {
	function wchs_custom_return_origins(): array {
		$settings = wchs_origin_settings_raw();
		$custom   = wchs_parse_origin_list( $settings['custom_return_origins'] ?? [] );
		if ( ! empty( $custom ) ) {
			return $custom;
		}

		return wchs_legacy_origin_constants()['return_origins'];
	}
}

if ( ! function_exists( 'wchs_spa_origin' ) ) {
	function wchs_spa_origin(): string {
		if ( 'custom' === wchs_origin_mode() ) {
			$custom = wchs_custom_spa_origin();
			if ( $custom !== '' ) {
				return $custom;
			}

			$allowed = wchs_custom_allowed_origins();
			if ( ! empty( $allowed[0] ) ) {
				return $allowed[0];
			}

			$return = wchs_custom_return_origins();
			if ( ! empty( $return[0] ) ) {
				return $return[0];
			}
		}

		return wchs_public_origin();
	}
}

if ( ! function_exists( 'wchs_allowed_origin_list' ) ) {
	function wchs_allowed_origin_list(): array {
		if ( 'custom' === wchs_origin_mode() ) {
			$allowed = wchs_custom_allowed_origins();
			if ( ! empty( $allowed ) ) {
				return $allowed;
			}

			return [ wchs_spa_origin() ];
		}

		return [ wchs_public_origin() ];
	}
}

if ( ! function_exists( 'wchs_return_origin_list' ) ) {
	function wchs_return_origin_list(): array {
		if ( 'custom' === wchs_origin_mode() ) {
			$return = wchs_custom_return_origins();
			if ( ! empty( $return ) ) {
				return $return;
			}

			return wchs_allowed_origin_list();
		}

		return [ wchs_public_origin() ];
	}
}

if ( ! function_exists( 'wchs_origin_report' ) ) {
	function wchs_origin_report(): array {
		$public   = wchs_public_origin();
		$siteurl  = wchs_normalize_origin( get_option( 'siteurl', '' ) );
		$home     = wchs_normalize_origin( get_option( 'home', '' ) );
		$mode     = wchs_origin_mode();
		$legacy   = wchs_legacy_origin_constants();
		$settings = wchs_origin_settings_raw();
		$spa      = wchs_spa_origin();
		$allowed  = wchs_allowed_origin_list();
		$return   = wchs_return_origin_list();
		$errors   = [];
		$warnings = [];

		if ( $siteurl !== '' && $home !== '' && $siteurl !== $home ) {
			$errors[] = 'siteurl and home do not match.';
		}

		if ( 'same-origin' === $mode ) {
			if ( $spa !== $public ) {
				$errors[] = 'WCHS SPA origin does not match the public site origin.';
			}
			if ( ! in_array( $public, $allowed, true ) ) {
				$errors[] = 'Allowed origins do not include the public site origin.';
			}
			if ( ! in_array( $public, $return, true ) ) {
				$errors[] = 'Return origins do not include the public site origin.';
			}

			if ( $legacy['spa_origin'] !== '' && $legacy['spa_origin'] !== $public ) {
				$warnings[] = 'Legacy WCHS_SPA_URL constant differs from the current public origin and is ignored in same-origin mode.';
			}
			if ( ! empty( $legacy['allowed_origins'] ) && ! in_array( $public, $legacy['allowed_origins'], true ) ) {
				$warnings[] = 'Legacy WCHS_ALLOWED_ORIGINS constants differ from the current public origin and are ignored in same-origin mode.';
			}
			if ( ! empty( $legacy['return_origins'] ) && ! in_array( $public, $legacy['return_origins'], true ) ) {
				$warnings[] = 'Legacy WCHS_RETURN_ORIGINS constants differ from the current public origin and are ignored in same-origin mode.';
			}
		} elseif ( $spa === '' ) {
			$errors[] = 'Custom mode is enabled but no custom SPA origin is configured.';
		}

		return [
			'mode'                   => $mode,
			'mode_source'            => wchs_origin_mode_source(),
			'public_origin'          => $public,
			'siteurl'                => $siteurl,
			'home'                   => $home,
			'spa_origin'             => $spa,
			'allowed_origins'        => $allowed,
			'return_origins'         => $return,
			'custom_spa_origin'      => wchs_normalize_origin( $settings['custom_spa_origin'] ?? '' ),
			'custom_allowed_origins' => wchs_parse_origin_list( $settings['custom_allowed_origins'] ?? [] ),
			'custom_return_origins'  => wchs_parse_origin_list( $settings['custom_return_origins'] ?? [] ),
			'legacy'                 => $legacy,
			'errors'                 => $errors,
			'warnings'               => $warnings,
		];
	}
}
