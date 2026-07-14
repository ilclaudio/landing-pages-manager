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
		$this->assertContains( 'is_canonical', $columns );
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
				'is_canonical' => 1,
				'lang'    => 'en',
			)
		);

		$this->assertIsInt( $mapping_id );

		$mapping = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		$this->assertSame( 'subdomain', $mapping['type'] );
		$this->assertSame( 'promo.example.com', $mapping['value'] );
		$this->assertSame( (string) $page_id, $mapping['page_id'] );
		$this->assertSame( '1', $mapping['active'] );
		$this->assertSame( '1', $mapping['is_canonical'] );
		$this->assertSame( 'en', $mapping['lang'] );

		$this->assertTrue(
			KKLPM_Domain_Map_Repository::update_mapping(
				$mapping_id,
				array(
					'type'    => 'subpath',
					'value'   => 'campaign/',
					'page_id' => $page_id,
					'active'  => 0,
					'is_canonical' => 0,
					'lang'    => '',
				)
			)
		);

		$updated_mapping = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		$this->assertSame( 'subpath', $updated_mapping['type'] );
		$this->assertSame( '/campaign', $updated_mapping['value'] );
		$this->assertSame( '0', $updated_mapping['active'] );
		$this->assertSame( '0', $updated_mapping['is_canonical'] );
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
	 * Ensures find_request_candidates() finds subdomain/external/subpath matches in one call.
	 *
	 * @return void
	 */
	public function test_find_request_candidates_matches_host_and_path_mappings_in_one_call() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$subdomain_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => 'promo.example.org',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$subpath_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'unrelated.example.net',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$candidates    = KKLPM_Domain_Map_Repository::find_request_candidates( 'promo.example.org', '/promo' );
		$candidate_ids = array_map( 'intval', wp_list_pluck( $candidates, 'id' ) );

		$this->assertCount( 2, $candidates );
		$this->assertContains( $subdomain_id, $candidate_ids );
		$this->assertContains( $subpath_id, $candidate_ids );
	}

	/**
	 * Ensures the canonical flag remains unique per page after inserts and updates.
	 *
	 * @return void
	 */
	public function test_repository_keeps_only_one_canonical_mapping_per_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$first_mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => 'promo.example.org',
				'page_id' => $page_id,
				'active'  => 1,
				'is_canonical' => 1,
			)
		);
		$second_mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'landing.example.net',
				'page_id' => $page_id,
				'active'  => 1,
				'is_canonical' => 1,
			)
		);

		$first_mapping  = KKLPM_Domain_Map_Repository::get_mapping( $first_mapping_id );
		$second_mapping = KKLPM_Domain_Map_Repository::get_mapping( $second_mapping_id );
		$canonical      = KKLPM_Domain_Map_Repository::get_canonical_mapping_for_page( $page_id );

		$this->assertSame( '0', $first_mapping['is_canonical'] );
		$this->assertSame( '1', $second_mapping['is_canonical'] );
		$this->assertSame( $second_mapping_id, (int) $canonical['id'] );
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
