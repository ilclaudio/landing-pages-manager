<?php
/**
 * Repository for landing page domain mappings.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles database persistence for landing page routing rules.
 */
class KKLPM_Domain_Map_Repository {

	/**
	 * Get the custom table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'kklpm_landing_domain_map';
	}

	/**
	 * Get the charset/collation string for table creation.
	 *
	 * @return string
	 */
	public static function get_charset_collate() {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * Create or update the domain mapping table.
	 *
	 * @return void
	 */
	public static function create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::get_create_table_sql() );
	}

	/**
	 * Build the SQL used by dbDelta to create the domain mapping table.
	 *
	 * @return string
	 */
	public static function get_create_table_sql() {
		$table_name      = self::get_table_name();
		$charset_collate = self::get_charset_collate();

		return "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			value varchar(255) NOT NULL,
			page_id bigint(20) unsigned NOT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			lang varchar(20) NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY type_value (type, value(191)),
			KEY page_id (page_id),
			KEY active (active)
		) {$charset_collate};";
	}

	/**
	 * Insert a new mapping row.
	 *
	 * @param array $data Mapping data.
	 * @return int|false
	 */
	public static function insert_mapping( array $data ) {
		global $wpdb;

		$prepared = self::prepare_mapping_data( $data );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional repository write for plugin-owned table.
		$result = $wpdb->insert(
			self::get_table_name(),
			$prepared,
			array( '%s', '%s', '%d', '%d', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing mapping row.
	 *
	 * @param int   $id   Mapping ID.
	 * @param array $data Mapping data.
	 * @return bool
	 */
	public static function update_mapping( $id, array $data ) {
		global $wpdb;

		$prepared = self::prepare_mapping_data( $data );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional repository write for plugin-owned table.
		$result = $wpdb->update(
			self::get_table_name(),
			$prepared,
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a mapping row.
	 *
	 * @param int $id Mapping ID.
	 * @return bool
	 */
	public static function delete_mapping( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional repository delete for plugin-owned table.
		$result = $wpdb->delete(
			self::get_table_name(),
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a single mapping row by ID.
	 *
	 * @param int $id Mapping ID.
	 * @return array|null
	 */
	public static function get_mapping( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Intentional repository read for plugin-owned table.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name.
				(int) $id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get all active mappings.
	 *
	 * @return array
	 */
	public static function get_active_mappings() {
		global $wpdb;
		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional repository read for plugin-owned table name.
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table_name} WHERE active = 1 ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name.
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find active candidate mappings by type and normalized value.
	 *
	 * @param string $type             Mapping type.
	 * @param string $normalized_value Normalized mapping value.
	 * @return array
	 */
	public static function find_matching_candidates( $type, $normalized_value ) {
		global $wpdb;
		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Intentional repository read for plugin-owned table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE active = 1 AND type = %s AND value = %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name.
				sanitize_key( $type ),
				(string) $normalized_value
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Normalize mapping data before persistence.
	 *
	 * @param array $data Raw mapping data.
	 * @return array
	 */
	protected static function prepare_mapping_data( array $data ) {
		$type = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : '';
		$lang = isset( $data['lang'] ) ? sanitize_text_field( (string) $data['lang'] ) : '';

		return array(
			'type'    => $type,
			'value'   => KKLPM_Domain_Router_Matcher::normalize_mapping_value(
				$type,
				isset( $data['value'] ) ? (string) $data['value'] : ''
			),
			'page_id' => isset( $data['page_id'] ) ? (int) $data['page_id'] : 0,
			'active'  => empty( $data['active'] ) ? 0 : 1,
			'lang'    => '' === $lang ? null : $lang,
		);
	}
}
