<?php
/**
 * Unit tests for KKLPM_Domain_Router_Matcher.
 *
 * @package LandingPageManager
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers pure host/path normalization and mapping logic.
 */
class KKLPM_Domain_Router_Matcher_Test extends TestCase {

	/**
	 * Ensure hosts are normalized for exact matching.
	 *
	 * @return void
	 */
	public function test_normalize_host_lowercases_and_removes_port() {
		$this->assertSame( 'promo.example.com', KKLPM_Domain_Router_Matcher::normalize_host( ' Promo.Example.com:8080 ' ) );
		$this->assertSame( 'landing.example.com', KKLPM_Domain_Router_Matcher::normalize_host( '.landing.example.com.' ) );
	}

	/**
	 * Ensure request paths are normalized with the shared path helper.
	 *
	 * @return void
	 */
	public function test_normalize_request_path_uses_shared_path_rules() {
		$this->assertSame( '/promo', KKLPM_Domain_Router_Matcher::normalize_request_path( 'promo/' ) );
		$this->assertSame( '/news/latest', KKLPM_Domain_Router_Matcher::normalize_request_path( '//news///latest/' ) );
	}

	/**
	 * Ensure host-based mappings normalize as hosts and subpaths as paths.
	 *
	 * @return void
	 */
	public function test_normalize_mapping_value_respects_mapping_type() {
		$this->assertSame( 'promo.example.com', KKLPM_Domain_Router_Matcher::normalize_mapping_value( 'subdomain', 'Promo.Example.com:443' ) );
		$this->assertSame( 'landing.example.org', KKLPM_Domain_Router_Matcher::normalize_mapping_value( 'external', ' Landing.Example.org ' ) );
		$this->assertSame( '/promo', KKLPM_Domain_Router_Matcher::normalize_mapping_value( 'subpath', 'promo/' ) );
	}

	/**
	 * Ensure the matcher finds host-based mappings before returning null.
	 *
	 * @return void
	 */
	public function test_match_request_returns_host_mapping_when_it_matches() {
		$mappings = array(
			array(
				'id'     => 10,
				'type'   => 'subdomain',
				'value'  => 'promo.example.com',
				'active' => 1,
			),
			array(
				'id'     => 11,
				'type'   => 'external',
				'value'  => 'promo-external.example.org',
				'active' => 1,
			),
		);

		$match = KKLPM_Domain_Router_Matcher::match_request( 'Promo.Example.com', '/unused', $mappings );

		$this->assertSame( 10, $match['id'] );
	}

	/**
	 * Ensure the matcher finds a subpath mapping when the path matches exactly.
	 *
	 * @return void
	 */
	public function test_match_request_returns_subpath_mapping_when_it_matches() {
		$mappings = array(
			array(
				'id'     => 21,
				'type'   => 'subpath',
				'value'  => '/promo',
				'active' => 1,
			),
		);

		$match = KKLPM_Domain_Router_Matcher::match_request( 'example.com', 'promo/', $mappings );

		$this->assertSame( 21, $match['id'] );
	}

	/**
	 * Ensure inactive or incompatible mappings do not match the request.
	 *
	 * @return void
	 */
	public function test_match_request_returns_null_when_no_active_mapping_matches() {
		$mappings = array(
			array(
				'id'     => 30,
				'type'   => 'subdomain',
				'value'  => 'promo.example.com',
				'active' => 0,
			),
			array(
				'id'     => 31,
				'type'   => 'subpath',
				'value'  => '/different',
				'active' => 1,
			),
		);

		$this->assertNull( KKLPM_Domain_Router_Matcher::match_request( 'promo.example.com', '/promo', $mappings ) );
	}
}
