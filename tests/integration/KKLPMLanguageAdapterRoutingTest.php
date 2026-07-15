<?php
/**
 * Integration tests for language-aware routing in the Domain Router module.
 *
 * @package LandingPageManager
 */

/**
 * Verifies KKLPM_Domain_Router_Module resolves translated pages through the
 * active language adapter, with a safe fallback to the source page.
 */
class KKLPMLanguageAdapterRoutingTest extends WP_UnitTestCase {

	/**
	 * Router module instance under test.
	 *
	 * @var KKLPM_Domain_Router_Module
	 */
	protected $module;

	/**
	 * Prepare a clean custom table, a fresh module instance, and a clean resolver cache.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		KKLPM_Domain_Map_Repository::create_table();
		$this->truncate_domain_map_table();
		$this->module = new KKLPM_Domain_Router_Module();
		KKLPM_Language_Adapter_Resolver::reset();
		$_SERVER['HTTP_HOST'] = 'example.org';
	}

	/**
	 * Reset table, server state, filters, and resolver cache after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->truncate_domain_map_table();
		unset( $_SERVER['HTTP_HOST'] );
		unset( $_GET['kklpm_lang'] );
		remove_all_filters( 'kklpm_language_adapter_priority' );
		KKLPM_Language_Adapter_Resolver::reset();
		parent::tear_down();
	}

	/**
	 * Ensures the router keeps resolving the source page unchanged when the
	 * Null adapter is active, i.e. no multilingual plugin is present.
	 *
	 * @return void
	 */
	public function test_router_keeps_source_page_when_null_adapter_is_active() {
		add_filter(
			'kklpm_language_adapter_priority',
			function () {
				return array( 'KKLPM_Language_Adapter_Null' );
			}
		);

		$page_id = $this->create_published_page( 'promo-page' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$wp          = new WP();
		$wp->request = 'promo';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $page_id, (int) $wp->query_vars['page_id'] );
	}

	/**
	 * Ensures the router resolves the translated page ID when the active
	 * adapter provides one for the current language.
	 *
	 * @return void
	 */
	public function test_router_resolves_translated_page_when_adapter_has_a_translation() {
		$source_id     = $this->create_published_page( 'promo-page' );
		$translated_id = $this->create_published_page( 'promo-page-fr' );

		KKLPM_Test_Fake_Language_Adapter::$current_language = 'fr';
		KKLPM_Test_Fake_Language_Adapter::$translations      = array( $source_id => $translated_id );

		add_filter(
			'kklpm_language_adapter_priority',
			function () {
				return array( 'KKLPM_Test_Fake_Language_Adapter' );
			}
		);

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $source_id,
				'active'  => 1,
			)
		);

		$wp          = new WP();
		$wp->request = 'promo';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $translated_id, (int) $wp->query_vars['page_id'] );
	}

	/**
	 * Ensures the router falls back to the source page when the active
	 * adapter has no translation for the current language.
	 *
	 * @return void
	 */
	public function test_router_falls_back_to_source_page_without_a_translation() {
		$source_id = $this->create_published_page( 'promo-page' );

		KKLPM_Test_Fake_Language_Adapter::$current_language = 'fr';
		KKLPM_Test_Fake_Language_Adapter::$translations      = array();

		add_filter(
			'kklpm_language_adapter_priority',
			function () {
				return array( 'KKLPM_Test_Fake_Language_Adapter' );
			}
		);

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $source_id,
				'active'  => 1,
			)
		);

		$wp          = new WP();
		$wp->request = 'promo';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $source_id, (int) $wp->query_vars['page_id'] );
	}

	/**
	 * Ensures an explicit `?kklpm_lang=` override wins over the active
	 * adapter's own current-language detection.
	 *
	 * @return void
	 */
	public function test_router_uses_language_override_over_adapter_current_language() {
		$source_id = $this->create_published_page( 'promo-page' );
		$fr_id     = $this->create_published_page( 'promo-page-fr' );
		$it_id     = $this->create_published_page( 'promo-page-it' );

		KKLPM_Test_Fake_Language_Adapter::$current_language = 'fr';
		KKLPM_Test_Fake_Language_Adapter::$translations      = array(
			$source_id => array(
				'fr' => $fr_id,
				'it' => $it_id,
			),
		);

		add_filter(
			'kklpm_language_adapter_priority',
			function () {
				return array( 'KKLPM_Test_Fake_Language_Adapter' );
			}
		);

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $source_id,
				'active'  => 1,
			)
		);

		$_GET['kklpm_lang'] = 'it';

		$wp          = new WP();
		$wp->request = 'promo';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $it_id, (int) $wp->query_vars['page_id'] );
	}

	/**
	 * Ensures an override for an untranslatable language falls back safely to
	 * the source page, without falling through to the adapter's own current
	 * language.
	 *
	 * @return void
	 */
	public function test_router_language_override_falls_back_to_source_page_without_a_translation() {
		$source_id = $this->create_published_page( 'promo-page' );
		$fr_id     = $this->create_published_page( 'promo-page-fr' );

		KKLPM_Test_Fake_Language_Adapter::$current_language = 'fr';
		KKLPM_Test_Fake_Language_Adapter::$translations      = array(
			$source_id => array( 'fr' => $fr_id ),
		);

		add_filter(
			'kklpm_language_adapter_priority',
			function () {
				return array( 'KKLPM_Test_Fake_Language_Adapter' );
			}
		);

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $source_id,
				'active'  => 1,
			)
		);

		$_GET['kklpm_lang'] = 'xx';

		$wp          = new WP();
		$wp->request = 'promo';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $source_id, (int) $wp->query_vars['page_id'] );
	}

	/**
	 * Creates a published page with a stable slug.
	 *
	 * @param string $slug Desired page slug.
	 * @return int
	 */
	protected function create_published_page( $slug ) {
		return self::factory()->post->create(
			array(
				'post_title'  => ucwords( str_replace( '-', ' ', $slug ) ),
				'post_name'   => $slug,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Removes all rows from the plugin-owned custom table.
	 *
	 * @return void
	 */
	protected function truncate_domain_map_table() {
		global $wpdb;
		$table_name = KKLPM_Domain_Map_Repository::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

/**
 * Test double: an always-active adapter with configurable translations.
 */
class KKLPM_Test_Fake_Language_Adapter implements KKLPM_Language_Adapter_Interface {

	/**
	 * Current language returned by the fake adapter.
	 *
	 * @var string|null
	 */
	public static $current_language = null;

	/**
	 * Translation map: `[ source_page_id => translated_page_id ]` for a single
	 * implicit language, or `[ source_page_id => [ lang => translated_page_id ] ]`
	 * when different languages must resolve to different pages.
	 *
	 * @var array
	 */
	public static $translations = array();

	/**
	 * Always active: this is a deliberately selected test double.
	 *
	 * @return bool
	 */
	public function is_active() {
		return true;
	}

	/**
	 * Returns the configured current language.
	 *
	 * @return string|null
	 */
	public function get_current_language() {
		return self::$current_language;
	}

	/**
	 * Returns an empty language list; not exercised by these tests.
	 *
	 * @return string[]
	 */
	public function get_available_languages() {
		return array();
	}

	/**
	 * Returns the configured translation for a source page/language, if any.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code.
	 * @return int|null
	 */
	public function get_translated_page_id( $page_id, $lang ) {
		if ( ! isset( self::$translations[ $page_id ] ) ) {
			return null;
		}

		$mapped = self::$translations[ $page_id ];

		if ( is_array( $mapped ) ) {
			return isset( $mapped[ $lang ] ) ? (int) $mapped[ $lang ] : null;
		}

		return (int) $mapped;
	}
}
