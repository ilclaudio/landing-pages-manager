<?php
/**
 * Unit tests for the Language Adapter interface, Null adapter, and resolver.
 *
 * @package LandingPageManager
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers adapter selection and the safe monolingual fallback.
 */
class KKLPM_Language_Adapter_Resolver_Test extends TestCase {

	/**
	 * Resets the resolver's request-scoped cache between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		KKLPM_Language_Adapter_Resolver::reset();
	}

	/**
	 * Ensure the resolver falls back to the Null adapter when no
	 * multilingual plugin is detected, which is always true in this
	 * WordPress-free unit environment.
	 *
	 * @return void
	 */
	public function test_resolver_falls_back_to_null_adapter_when_none_active() {
		$adapter = KKLPM_Language_Adapter_Resolver::get_active_adapter();

		$this->assertInstanceOf( KKLPM_Language_Adapter_Null::class, $adapter );
	}

	/**
	 * Ensure the resolver caches the resolved adapter for the request.
	 *
	 * @return void
	 */
	public function test_resolver_caches_the_resolved_adapter() {
		$first  = KKLPM_Language_Adapter_Resolver::get_active_adapter();
		$second = KKLPM_Language_Adapter_Resolver::get_active_adapter();

		$this->assertSame( $first, $second );
	}

	/**
	 * Ensure the Null adapter keeps single-language behavior unchanged.
	 *
	 * @return void
	 */
	public function test_null_adapter_behaves_as_safe_fallback() {
		$adapter = new KKLPM_Language_Adapter_Null();

		$this->assertTrue( $adapter->is_active() );
		$this->assertNull( $adapter->get_current_language() );
		$this->assertSame( array(), $adapter->get_available_languages() );
		$this->assertSame( 42, $adapter->get_translated_page_id( 42, 'it' ) );
	}

	/**
	 * Ensure each concrete adapter reports inactive when its plugin markers
	 * are absent, which is the case in this WordPress-free unit environment.
	 *
	 * @return void
	 */
	public function test_concrete_adapters_report_inactive_without_their_plugin() {
		$this->assertFalse( ( new KKLPM_Language_Adapter_WPML() )->is_active() );
		$this->assertFalse( ( new KKLPM_Language_Adapter_Polylang() )->is_active() );
		$this->assertFalse( ( new KKLPM_Language_Adapter_TranslatePress() )->is_active() );
		$this->assertFalse( ( new KKLPM_Language_Adapter_Weglot() )->is_active() );
		$this->assertFalse( ( new KKLPM_Language_Adapter_MultilingualPress() )->is_active() );
	}

	/**
	 * Ensure inactive concrete adapters return null/empty instead of touching
	 * any WordPress or third-party API.
	 *
	 * @return void
	 */
	public function test_inactive_concrete_adapters_return_safe_defaults() {
		$adapter = new KKLPM_Language_Adapter_WPML();

		$this->assertNull( $adapter->get_current_language() );
		$this->assertSame( array(), $adapter->get_available_languages() );
		$this->assertNull( $adapter->get_translated_page_id( 42, 'it' ) );
	}
}
