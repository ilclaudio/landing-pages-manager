<?php
/**
 * Unit tests for KKLPM_Path_Utils.
 *
 * @package LandingPageManager
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers path normalization helpers.
 */
class KKLPMPathUtilsTest extends TestCase {

	/**
	 * Ensure empty values normalize to the root path.
	 *
	 * @return void
	 */
	public function test_normalize_route_path_returns_root_for_empty_string() {
		$this->assertSame( '/', KKLPM_Path_Utils::normalize_route_path( '' ) );
	}

	/**
	 * Ensure a path without a leading slash gets normalized.
	 *
	 * @return void
	 */
	public function test_normalize_route_path_adds_leading_slash() {
		$this->assertSame( '/astronomia', KKLPM_Path_Utils::normalize_route_path( 'astronomia' ) );
	}

	/**
	 * Ensure repeated and trailing slashes are cleaned up.
	 *
	 * @return void
	 */
	public function test_normalize_route_path_collapses_duplicate_and_trailing_slashes() {
		$this->assertSame( '/news/latest', KKLPM_Path_Utils::normalize_route_path( '//news///latest/' ) );
	}
}
