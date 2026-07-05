<?php
/**
 * Integration tests for the domain map repository.
 *
 * @package LandingPageManager
 */

/**
 * Verifies custom table CRUD and normalization behavior.
 */
class KKLPMDomainRouterRepositoryTest extends WP_UnitTestCase {

	/**
	 * Cleans the plugin-owned table before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		KKLPM_Domain_Map_Repository::create_table();
		$this->truncate_domain_map_table();
	}

	/**
	 * Cleans the plugin-owned table after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->truncate_domain_map_table();
		parent::tear_down();
	}

	/**
	 * Ensures the custom table exists after activation/bootstrap.
	 *
	 * @return void
	 */
	public function test_domain_map_table_exists() {
		global $wpdb;

		$table_name = KKLPM_Domain_Map_Repository::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col( "DESC {$table_name}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name.

		$this->assertIsArray( $columns );
		$this->assertNotEmpty( $columns );
		$this->assertContains( 'id', $columns );
		$this->assertContains( 'type', $columns );
		$this->assertContains( 'value', $columns );
		$this->assertContains( 'page_id', $columns );
		$this->assertContains( 'active', $columns );
		$this->assertContains( 'lang', $columns );
	}

	/**
	 * Ensures CRUD operations persist normalized data, including `lang`.
	 *
	 * @return void
	 */
	public function test_repository_insert_update_and_delete_mapping() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => ' Promo.Example.com:443 ',
				'page_id' => $page_id,
				'active'  => 1,
				'lang'    => 'en',
			)
		);

		$this->assertIsInt( $mapping_id );

		$mapping = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		$this->assertSame( 'subdomain', $mapping['type'] );
		$this->assertSame( 'promo.example.com', $mapping['value'] );
		$this->assertSame( (string) $page_id, $mapping['page_id'] );
		$this->assertSame( '1', $mapping['active'] );
		$this->assertSame( 'en', $mapping['lang'] );

		$this->assertTrue(
			KKLPM_Domain_Map_Repository::update_mapping(
				$mapping_id,
				array(
					'type'    => 'subpath',
					'value'   => 'campaign/',
					'page_id' => $page_id,
					'active'  => 0,
					'lang'    => '',
				)
			)
		);

		$updated_mapping = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		$this->assertSame( 'subpath', $updated_mapping['type'] );
		$this->assertSame( '/campaign', $updated_mapping['value'] );
		$this->assertSame( '0', $updated_mapping['active'] );
		$this->assertNull( $updated_mapping['lang'] );

		$this->assertTrue( KKLPM_Domain_Map_Repository::delete_mapping( $mapping_id ) );
		$this->assertNull( KKLPM_Domain_Map_Repository::get_mapping( $mapping_id ) );
	}

	/**
	 * Ensures active candidate queries use normalized values and filters.
	 *
	 * @return void
	 */
	public function test_repository_returns_active_matching_candidates_only() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$active_mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'Landing.Example.org',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'landing.example.org',
				'page_id' => $page_id,
				'active'  => 0,
			)
		);

		$candidates = KKLPM_Domain_Map_Repository::find_matching_candidates( 'external', 'landing.example.org' );

		$this->assertCount( 1, $candidates );
		$this->assertSame( $active_mapping_id, (int) $candidates[0]['id'] );
	}

	/**
	 * Ensures unsupported mapping types are rejected early.
	 *
	 * @return void
	 */
	public function test_repository_rejects_unsupported_mapping_type() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$this->assertFalse(
			KKLPM_Domain_Map_Repository::insert_mapping(
				array(
					'type'    => 'wildcard',
					'value'   => '*.example.org',
					'page_id' => $page_id,
					'active'  => 1,
				)
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
