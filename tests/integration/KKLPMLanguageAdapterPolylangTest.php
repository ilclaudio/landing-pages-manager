<?php
/**
 * Integration tests for the Polylang language adapter.
 *
 * Polylang itself is not bootstrapped inside WP_UnitTestCase; instead these
 * tests exercise the adapter against the guarded pll_*() shims defined in
 * tests/bootstrap-integration.php, the same technique already used for ACF.
 *
 * @package LandingPageManager
 */

/**
 * Verifies the Polylang adapter wraps the shimmed Polylang functions correctly.
 */
class KKLPMLanguageAdapterPolylangTest extends WP_UnitTestCase {

	/**
	 * Clears shim state before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->clear_polylang_shim_state();
	}

	/**
	 * Clears shim state after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->clear_polylang_shim_state();
		remove_all_filters( 'pll_check_canonical_url' );

		global $wp;
		unset( $wp->matched_rule );

		parent::tear_down();
	}

	/**
	 * Ensures the adapter detects the shimmed Polylang functions as active.
	 *
	 * @return void
	 */
	public function test_adapter_is_active_when_polylang_functions_exist() {
		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertTrue( $adapter->is_active() );
	}

	/**
	 * Ensures the adapter reads the current language from Polylang.
	 *
	 * @return void
	 */
	public function test_adapter_reads_current_language() {
		$GLOBALS['kklpm_test_pll_current_language'] = 'fr';

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertSame( 'fr', $adapter->get_current_language() );
	}

	/**
	 * Ensures the adapter reads the configured languages list from Polylang.
	 *
	 * @return void
	 */
	public function test_adapter_reads_available_languages() {
		$GLOBALS['kklpm_test_pll_languages_list'] = array( 'it', 'en', 'fr' );

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertSame( array( 'it', 'en', 'fr' ), $adapter->get_available_languages() );
	}

	/**
	 * Ensures the adapter resolves a translated page ID from Polylang.
	 *
	 * @return void
	 */
	public function test_adapter_resolves_translated_page_id() {
		$source_id     = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$translated_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$GLOBALS['kklpm_test_pll_translations'] = array(
			$source_id => array( 'fr' => $translated_id ),
		);

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertSame( $translated_id, $adapter->get_translated_page_id( $source_id, 'fr' ) );
	}

	/**
	 * Ensures the adapter returns null when Polylang has no translation.
	 *
	 * @return void
	 */
	public function test_adapter_returns_null_without_a_translation() {
		$source_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertNull( $adapter->get_translated_page_id( $source_id, 'fr' ) );
	}

	/**
	 * Ensures the adapter resolves the default-language source page ID for a translation.
	 *
	 * @return void
	 */
	public function test_adapter_resolves_source_page_id() {
		$source_id     = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$translated_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$GLOBALS['kklpm_test_pll_default_language'] = 'en';
		$GLOBALS['kklpm_test_pll_translations']      = array(
			$translated_id => array( 'en' => $source_id ),
		);

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertSame( $source_id, $adapter->get_source_page_id( $translated_id ) );
	}

	/**
	 * Ensures the adapter returns null when the page is the default-language
	 * page itself, i.e. there is no different source page to inherit from.
	 *
	 * @return void
	 */
	public function test_adapter_returns_null_for_source_page_id_when_page_is_already_the_source() {
		$source_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$GLOBALS['kklpm_test_pll_default_language'] = 'en';
		$GLOBALS['kklpm_test_pll_translations']      = array(
			$source_id => array( 'en' => $source_id ),
		);

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertNull( $adapter->get_source_page_id( $source_id ) );
	}

	/**
	 * Ensures the adapter returns null when there is no default-language
	 * translation registered for the page at all.
	 *
	 * @return void
	 */
	public function test_adapter_returns_null_for_source_page_id_without_a_default_language_translation() {
		$translated_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$GLOBALS['kklpm_test_pll_default_language'] = 'en';

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertNull( $adapter->get_source_page_id( $translated_id ) );
	}

	/**
	 * Ensures `register()` hooks Polylang's own canonical-redirect filter.
	 *
	 * @return void
	 */
	public function test_register_hooks_canonical_redirect_filter() {
		$adapter = new KKLPM_Language_Adapter_Polylang();
		$adapter->register();

		$this->assertNotFalse( has_filter( 'pll_check_canonical_url', array( $adapter, 'maybe_skip_canonical_redirect' ) ) );
	}

	/**
	 * Ensures the canonical redirect is suppressed for requests already
	 * routed by the Domain Router.
	 *
	 * @return void
	 */
	public function test_maybe_skip_canonical_redirect_suppresses_redirect_for_routed_requests() {
		global $wp;
		$wp->matched_rule = KKLPM_Domain_Router_Module::MATCHED_RULE;

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertFalse( $adapter->maybe_skip_canonical_redirect( 'https://example.test/it/promo/' ) );
	}

	/**
	 * Ensures the canonical redirect is left untouched for requests not
	 * routed by the Domain Router.
	 *
	 * @return void
	 */
	public function test_maybe_skip_canonical_redirect_leaves_other_requests_untouched() {
		global $wp;
		unset( $wp->matched_rule );

		$adapter = new KKLPM_Language_Adapter_Polylang();

		$this->assertSame(
			'https://example.test/it/some-post/',
			$adapter->maybe_skip_canonical_redirect( 'https://example.test/it/some-post/' )
		);
	}

	/**
	 * Clears every global used by the Polylang shim functions.
	 *
	 * @return void
	 */
	protected function clear_polylang_shim_state() {
		unset( $GLOBALS['kklpm_test_pll_current_language'] );
		unset( $GLOBALS['kklpm_test_pll_languages_list'] );
		unset( $GLOBALS['kklpm_test_pll_translations'] );
		unset( $GLOBALS['kklpm_test_pll_default_language'] );
	}
}
