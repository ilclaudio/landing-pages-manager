<?php
/**
 * Path utility helpers for Landing Pages Manager.
 *
 * @package LandingPageManager
 */

/**
 * Utility methods for normalizing landing page route paths.
 */
class KKLPM_Path_Utils {

	/**
	 * Normalize a route path for internal comparisons.
	 *
	 * Examples:
	 * - `landing-page` becomes `/landing-page`
	 * - `/landing-page/` becomes `/landing-page`
	 * - empty values become `/`
	 *
	 * @param string $path Raw path value.
	 * @return string
	 */
	public static function normalize_route_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '/';
		}

		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		if ( 1 < strlen( $path ) ) {
			$path = rtrim( $path, '/' );
		}

		return $path;
	}
}
