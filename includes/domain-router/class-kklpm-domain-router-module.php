<?php
/**
 * Runtime routing module for landing page domain mappings.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves incoming requests against configured landing page mappings.
 */
class KKLPM_Domain_Router_Module {

	/**
	 * Custom marker used when the router applies a mapped page request.
	 *
	 * @var string
	 */
	const MATCHED_RULE = 'kklpm-domain-router';

	/**
	 * Registers module hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'parse_request', array( $this, 'handle_parse_request' ) );
	}

	/**
	 * Maps a request to a landing page before the main query runs.
	 *
	 * @param WP $wp Current WordPress request object.
	 * @return void
	 */
	public function handle_parse_request( $wp ) {
		if ( ! $wp instanceof WP || $this->should_bypass_request() ) {
			return;
		}

		$host        = $this->get_request_host();
		$path        = $this->get_request_path( $wp );
		$resolved_id = $this->resolve_target_page_id( $host, $path );

		if ( null === $resolved_id ) {
			return;
		}

		if ( false === $resolved_id ) {
			$this->apply_not_found( $wp );
			return;
		}

		$this->apply_page_request( $wp, $resolved_id );
	}

	/**
	 * Resolve the landing page ID for the current host/path pair.
	 *
	 * Returns:
	 * - `null` when no mapping matches the request
	 * - `false` when a mapping matches but its target page is invalid
	 * - `int` when a valid target page exists
	 *
	 * @param string $host Request host.
	 * @param string $path Request path.
	 * @return int|false|null
	 */
	public function resolve_target_page_id( $host, $path ) {
		$normalized_path = KKLPM_Domain_Router_Matcher::normalize_request_path( $path );
		$match           = $this->find_matching_mapping( $host, $normalized_path );

		if ( null === $match ) {
			return null;
		}

		$page_id = isset( $match['page_id'] ) ? (int) $match['page_id'] : 0;
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( $this->is_colliding_subpath_mapping( $match, $normalized_path, $page_id ) ) {
			return null;
		}

		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
			return false;
		}

		return $page_id;
	}

	/**
	 * Apply a resolved page request to the main `WP` object.
	 *
	 * @param WP  $wp      Current WordPress request object.
	 * @param int $page_id Target page ID.
	 * @return void
	 */
	public function apply_page_request( $wp, $page_id ) {
		$page_path         = get_page_uri( $page_id );
		$wp->query_vars    = array(
			'page_id'   => $page_id,
			'pagename'  => $page_path,
			'post_type' => 'page',
		);
		$wp->query_string  = 'page_id=' . $page_id;
		$wp->request       = $page_path;
		$wp->matched_rule  = self::MATCHED_RULE;
		$wp->matched_query = 'page_id=' . $page_id;
		$wp->did_permalink = true;

		unset( $wp->query_vars['error'] );
	}

	/**
	 * Mark the request as not found when a mapping exists but is unusable.
	 *
	 * @param WP $wp Current WordPress request object.
	 * @return void
	 */
	public function apply_not_found( $wp ) {
		$wp->query_vars    = array(
			'error' => '404',
		);
		$wp->query_string  = 'error=404';
		$wp->matched_rule  = self::MATCHED_RULE;
		$wp->matched_query = 'error=404';
	}

	/**
	 * Whether the current request should bypass custom routing.
	 *
	 * @return bool
	 */
	protected function should_bypass_request() {
		if ( is_admin() || wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the current request host from server state.
	 *
	 * @return string
	 */
	protected function get_request_host() {
		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
	}

	/**
	 * Get the current request path from the `WP` request object.
	 *
	 * @param WP $wp Current WordPress request object.
	 * @return string
	 */
	protected function get_request_path( $wp ) {
		$request = isset( $wp->request ) ? (string) $wp->request : '';

		return KKLPM_Domain_Router_Matcher::normalize_request_path( $request );
	}

	/**
	 * Find the first mapping matching the current request.
	 *
	 * @param string $host Request host.
	 * @param string $path Request path.
	 * @return array|null
	 */
	protected function find_matching_mapping( $host, $path ) {
		$normalized_host = KKLPM_Domain_Router_Matcher::normalize_host( $host );
		$normalized_path = KKLPM_Domain_Router_Matcher::normalize_request_path( $path );
		$candidates      = array_merge(
			KKLPM_Domain_Map_Repository::find_matching_candidates( 'subdomain', $normalized_host ),
			KKLPM_Domain_Map_Repository::find_matching_candidates( 'external', $normalized_host ),
			KKLPM_Domain_Map_Repository::find_matching_candidates( 'subpath', $normalized_path )
		);

		return KKLPM_Domain_Router_Matcher::match_request(
			$normalized_host,
			$normalized_path,
			$candidates
		);
	}

	/**
	 * Whether a matched subpath mapping collides with an existing page path.
	 *
	 * @param array  $matched_mapping Matched mapping row.
	 * @param string $normalized_path Normalized request path.
	 * @param int    $target_page_id  Target mapped page ID.
	 * @return bool
	 */
	protected function is_colliding_subpath_mapping( array $matched_mapping, $normalized_path, $target_page_id ) {
		if ( 'subpath' !== (string) $matched_mapping['type'] ) {
			return false;
		}

		$requested_path = trim( $normalized_path, '/' );

		if ( '' === $requested_path ) {
			return false;
		}

		$existing_page = get_page_by_path( $requested_path );

		return $existing_page instanceof WP_Post && (int) $existing_page->ID !== $target_page_id;
	}
}
