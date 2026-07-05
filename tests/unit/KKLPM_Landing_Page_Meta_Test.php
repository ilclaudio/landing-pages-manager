<?php
/**
 * Unit tests for KKLPM_Landing_Page_Meta constants.
 *
 * @package LandingPageManager
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers landing page meta definitions.
 */
class KKLPM_Landing_Page_Meta_Test extends TestCase {

	/**
	 * Ensure landing page meta keys remain stable.
	 *
	 * @return void
	 */
	public function test_meta_keys_match_expected_values() {
		$this->assertSame( '_kklpm_landing_enabled', KKLPM_Landing_Page_Meta::ENABLED );
		$this->assertSame( '_kklpm_landing_html', KKLPM_Landing_Page_Meta::HTML );
		$this->assertSame( '_kklpm_landing_css', KKLPM_Landing_Page_Meta::CSS );
		$this->assertSame( '_kklpm_landing_js', KKLPM_Landing_Page_Meta::JS );
		$this->assertSame( '_kklpm_landing_theme_header_footer', KKLPM_Landing_Page_Meta::THEME_HEADER_FOOTER );
		$this->assertSame( '_kklpm_landing_wp_assets', KKLPM_Landing_Page_Meta::WP_ASSETS );
	}
}
