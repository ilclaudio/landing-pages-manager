<?php
/**
 * Pure matching helpers for the domain router.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Matches incoming requests against configured landing page mappings.
 */
class KKLPM_Domain_Router_Matcher {

	/**
	 * Normalize a host value for exact matching.
	 *
	 * @param string $host Raw host value, optionally including a port.
	 * @return string
	 */
	public static function normalize_host( $host ) {
		$host = trim( strtolower( (string) $host ) );
		$host = preg_replace( '/:\d+$/', '', $host );
		$host = trim( $host, '.' );

		return $host;
	}

	/**
	 * Normalize a request path for exact matching.
	 *
	 * @param string $path Raw path value.
	 * @return string
	 */
	public static function normalize_request_path( $path ) {
		return KKLPM_Path_Utils::normalize_route_path( $path );
	}

	/**
	 * Normalize a mapping value based on its routing type.
	 *
	 * @param string $type  Mapping type.
	 * @param string $value Raw mapping value.
	 * @return string
	 */
	public static function normalize_mapping_value( $type, $value ) {
		if ( 'subpath' === $type ) {
			return self::normalize_request_path( $value );
		}

		return self::normalize_host( $value );
	}

	/**
	 * Find the first matching active mapping for a request.
	 *
	 * @param string $host     Request host.
	 * @param string $path     Request path.
	 * @param array  $mappings Candidate mappings.
	 * @return array|null
	 */
	public static function match_request( $host, $path, array $mappings ) {
		$normalized_host = self::normalize_host( $host );
		$normalized_path = self::normalize_request_path( $path );

		foreach ( $mappings as $mapping ) {
			if ( self::mapping_matches( $mapping, $normalized_host, $normalized_path ) ) {
				return $mapping;
			}
		}

		return null;
	}

	/**
	 * Whether a single mapping matches a normalized request.
	 *
	 * @param array  $mapping         Mapping data.
	 * @param string $normalized_host Normalized request host.
	 * @param string $normalized_path Normalized request path.
	 * @return bool
	 */
	public static function mapping_matches( array $mapping, $normalized_host, $normalized_path ) {
		if ( empty( $mapping['active'] ) || empty( $mapping['type'] ) || empty( $mapping['value'] ) ) {
			return false;
		}

		$type             = (string) $mapping['type'];
		$normalized_value = self::normalize_mapping_value( $type, $mapping['value'] );

		if ( 'subpath' === $type ) {
			return $normalized_path === $normalized_value;
		}

		if ( 'subdomain' === $type || 'external' === $type ) {
			return $normalized_host === $normalized_value;
		}

		return false;
	}
}
