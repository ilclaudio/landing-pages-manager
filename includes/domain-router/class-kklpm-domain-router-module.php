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
	 * Query var holding the mapped source page ID for the current routed request.
	 *
	 * Always the page a mapping was configured against, even when the
	 * request actually renders a translated page. Mappings are keyed by
	 * source page, so canonical lookups must use this, not the resolved ID.
	 *
	 * @var string
	 */
	const SOURCE_PAGE_ID_QUERY_VAR = 'kklpm_source_page_id';

	/**
	 * Query var holding the final resolved page ID for the current routed request.
	 *
	 * The page ID actually applied to the main query — the source page, or
	 * its translation when one was resolved.
	 *
	 * @var string
	 */
	const RESOLVED_PAGE_ID_QUERY_VAR = 'kklpm_resolved_page_id';

	/**
	 * Registers module hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'parse_request', array( $this, 'handle_parse_request' ) );
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical_url' ) );
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

		$host    = $this->get_request_host();
		$path    = $this->get_request_path( $wp );
		$context = $this->resolve_target_page_context( $host, $path );

		if ( null === $context ) {
			return;
		}

		if ( false === $context ) {
			$this->apply_not_found( $wp );
			return;
		}

		$this->apply_page_request( $wp, $context['source_page_id'], $context['resolved_page_id'] );
	}

	/**
	 * Resolve the landing page ID for the current host/path pair.
	 *
	 * Thin convenience wrapper around resolve_target_page_context() for
	 * callers that only need the final resolved page ID, not the source page
	 * a translated result was resolved from.
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
		$context = $this->resolve_target_page_context( $host, $path );

		if ( ! is_array( $context ) ) {
			return $context;
		}

		return $context['resolved_page_id'];
	}

	/**
	 * Resolve the landing page context for the current host/path pair.
	 *
	 * Unlike resolve_target_page_id(), this also exposes the mapped source
	 * page ID alongside the final (possibly translated) resolved page ID, so
	 * callers that need to look up mappings — which are always keyed by the
	 * source page, never by a translated page — do not have to re-derive it.
	 *
	 * Returns:
	 * - `null` when no mapping matches the request
	 * - `false` when a mapping matches but its target page is invalid
	 * - `array{source_page_id: int, resolved_page_id: int}` when a valid target page exists
	 *
	 * @param string $host Request host.
	 * @param string $path Request path.
	 * @return array|false|null
	 */
	protected function resolve_target_page_context( $host, $path ) {
		$normalized_path = KKLPM_Domain_Router_Matcher::normalize_request_path( $path );
		$match           = $this->find_matching_mapping( $host, $normalized_path );

		if ( null === $match ) {
			return null;
		}

		$page_id = isset( $match['page_id'] ) ? (int) $match['page_id'] : 0;

		if ( $this->is_colliding_subpath_mapping( $match, $normalized_path, $page_id ) ) {
			return null;
		}

		if ( ! $this->is_valid_routed_page( $page_id ) ) {
			return false;
		}

		$this->maybe_log_non_landing_mapping_warning( $match, $page_id, $host, $normalized_path );

		return array(
			'source_page_id'   => $page_id,
			'resolved_page_id' => $this->resolve_translated_page_id( $page_id, $match, $host, $normalized_path ),
		);
	}

	/**
	 * Logs a warning when a mapping targets a page without landing mode enabled.
	 *
	 * @param array  $matched_mapping Matched mapping row.
	 * @param int    $page_id         Matched page ID.
	 * @param string $host            Request host.
	 * @param string $normalized_path Normalized request path.
	 * @return void
	 */
	protected function maybe_log_non_landing_mapping_warning( array $matched_mapping, $page_id, $host, $normalized_path ) {
		if ( ! $this->should_log_non_landing_mapping_warning() || KKLPM_Landing_Page_Meta::is_enabled( $page_id ) ) {
			return;
		}

		$this->write_warning_log(
			sprintf(
				'KKLPM warning: matched mapping "%1$s:%2$s" to page %3$d, but landing page mode is disabled for that page. Request host="%4$s" path="%5$s".',
				isset( $matched_mapping['type'] ) ? (string) $matched_mapping['type'] : '',
				isset( $matched_mapping['value'] ) ? (string) $matched_mapping['value'] : '',
				(int) $page_id,
				(string) $host,
				(string) $normalized_path
			)
		);
	}

	/**
	 * Whether non-landing mapping warnings should be written to the PHP log.
	 *
	 * @return bool
	 */
	protected function should_log_non_landing_mapping_warning() {
		if ( defined( 'KKLPM_RUNNING_TESTS' ) && KKLPM_RUNNING_TESTS ) {
			return false;
		}

		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Writes a warning message to the PHP error log.
	 *
	 * @param string $message Warning message.
	 * @return void
	 */
	protected function write_warning_log( $message ) {
		error_log( (string) $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug warning for misconfigured mapped pages when WP_DEBUG is enabled.
	}

	/**
	 * Resolves the translated page ID for the current language, when available.
	 *
	 * Falls back to the source page ID when no multilingual plugin is active,
	 * or when the active adapter has no translation for the current language.
	 *
	 * @param int    $page_id         Source page ID matched by the router.
	 * @param array  $matched_mapping Matched mapping row.
	 * @param string $host            Request host.
	 * @param string $normalized_path Normalized request path.
	 * @return int
	 */
	protected function resolve_translated_page_id( $page_id, array $matched_mapping, $host, $normalized_path ) {
		$adapter = KKLPM_Language_Adapter_Resolver::get_active_adapter();
		$lang    = $this->get_language_override();

		if ( null === $lang ) {
			$lang = $adapter->get_current_language();
		}

		if ( null === $lang ) {
			return $page_id;
		}

		$translated_id = $adapter->get_translated_page_id( $page_id, $lang );

		if ( null === $translated_id || (int) $translated_id === (int) $page_id ) {
			return $page_id;
		}

		$translated_id = (int) $translated_id;

		if ( ! $this->is_valid_routed_page( $translated_id ) ) {
			$this->maybe_log_invalid_translated_page_warning( $matched_mapping, $page_id, $translated_id, $lang, $host, $normalized_path );

			return $page_id;
		}

		if ( ! KKLPM_Landing_Page_Meta::is_enabled( $translated_id ) ) {
			$this->maybe_log_non_landing_translation_warning( $matched_mapping, $page_id, $translated_id, $lang, $host, $normalized_path );

			return $page_id;
		}

		return $translated_id;
	}

	/**
	 * Whether a page ID can be served safely by the router.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	protected function is_valid_routed_page( $page_id ) {
		$page = $page_id ? get_post( $page_id ) : null;

		return $page instanceof WP_Post && 'page' === $page->post_type && 'trash' !== $page->post_status;
	}

	/**
	 * Logs a warning when a translated page cannot be served safely.
	 *
	 * @param array  $matched_mapping Matched mapping row.
	 * @param int    $source_page_id  Source page ID.
	 * @param int    $translated_id   Translated page ID returned by the adapter.
	 * @param string $lang            Requested language code.
	 * @param string $host            Request host.
	 * @param string $normalized_path Normalized request path.
	 * @return void
	 */
	protected function maybe_log_invalid_translated_page_warning( array $matched_mapping, $source_page_id, $translated_id, $lang, $host, $normalized_path ) {
		if ( ! $this->should_log_non_landing_mapping_warning() ) {
			return;
		}

		$this->write_warning_log(
			sprintf(
				'KKLPM warning: translation fallback to source page %1$d because translated page %2$d for language "%3$s" is invalid for mapping "%4$s:%5$s". Request host="%6$s" path="%7$s".',
				(int) $source_page_id,
				(int) $translated_id,
				(string) $lang,
				isset( $matched_mapping['type'] ) ? (string) $matched_mapping['type'] : '',
				isset( $matched_mapping['value'] ) ? (string) $matched_mapping['value'] : '',
				(string) $host,
				(string) $normalized_path
			)
		);
	}

	/**
	 * Logs a warning when a translated page exists but is not configured as a landing page.
	 *
	 * @param array  $matched_mapping Matched mapping row.
	 * @param int    $source_page_id  Source page ID.
	 * @param int    $translated_id   Translated page ID returned by the adapter.
	 * @param string $lang            Requested language code.
	 * @param string $host            Request host.
	 * @param string $normalized_path Normalized request path.
	 * @return void
	 */
	protected function maybe_log_non_landing_translation_warning( array $matched_mapping, $source_page_id, $translated_id, $lang, $host, $normalized_path ) {
		if ( ! $this->should_log_non_landing_mapping_warning() ) {
			return;
		}

		$this->write_warning_log(
			sprintf(
				'KKLPM warning: translation fallback to source page %1$d because translated page %2$d for language "%3$s" is not enabled as a landing page for mapping "%4$s:%5$s". Request host="%6$s" path="%7$s".',
				(int) $source_page_id,
				(int) $translated_id,
				(string) $lang,
				isset( $matched_mapping['type'] ) ? (string) $matched_mapping['type'] : '',
				isset( $matched_mapping['value'] ) ? (string) $matched_mapping['value'] : '',
				(string) $host,
				(string) $normalized_path
			)
		);
	}

	/**
	 * Reads an explicit visitor-requested language override, when present.
	 *
	 * Lets a visitor pick a specific language for a mapped request (e.g. via a
	 * manually authored switcher link or a direct link) instead of relying on
	 * the active adapter's own current-language detection.
	 *
	 * @return string|null
	 */
	protected function get_language_override() {
		if ( empty( $_GET['kklpm_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only language selector, not a state-changing action.
			return null;
		}

		$override = sanitize_text_field( wp_unslash( $_GET['kklpm_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only language selector, not a state-changing action.

		return '' !== $override ? $override : null;
	}

	/**
	 * Apply a resolved page request to the main `WP` object.
	 *
	 * @param WP  $wp               Current WordPress request object.
	 * @param int $source_page_id   Mapped source page ID (owner of the mapping table row).
	 * @param int $resolved_page_id Final page ID to actually render (source or its translation).
	 * @return void
	 */
	public function apply_page_request( $wp, $source_page_id, $resolved_page_id ) {
		$page_path         = get_page_uri( $resolved_page_id );
		$wp->query_vars    = array(
			'page_id'                        => $resolved_page_id,
			'pagename'                       => $page_path,
			'post_type'                      => 'page',
			self::SOURCE_PAGE_ID_QUERY_VAR   => (int) $source_page_id,
			self::RESOLVED_PAGE_ID_QUERY_VAR => (int) $resolved_page_id,
		);
		$wp->query_string  = 'page_id=' . $resolved_page_id;
		$wp->request       = $page_path;
		$wp->matched_rule  = self::MATCHED_RULE;
		$wp->matched_query = 'page_id=' . $resolved_page_id;
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
	 * Overrides WordPress core's canonical URL for the current routed request.
	 *
	 * Hooked on `get_canonical_url`, WP core's own canonical extension point
	 * (applied by `wp_get_canonical_url()`, consumed by `rel_canonical()` on
	 * `wp_head`). Integrating here — instead of printing a second tag
	 * directly on `wp_head` — guarantees exactly one canonical tag whenever
	 * `wp_head()` runs, letting WP core (or any theme/plugin that also
	 * respects this filter) do the actual printing.
	 *
	 * @param string $canonical_url Canonical URL WordPress core computed.
	 * @return string
	 */
	public function filter_canonical_url( $canonical_url ) {
		$override = self::get_canonical_url_for_request();

		return '' !== $override ? $override : $canonical_url;
	}

	/**
	 * Resolves the canonical URL for the current routed request.
	 *
	 * Single source of truth for "which page ID do I resolve the canonical
	 * from" for the current request, so no call site has to guess or
	 * re-derive it independently. Mappings — and therefore canonical
	 * ownership — always belong to the source page a request was routed
	 * from (see SOURCE_PAGE_ID_QUERY_VAR), never to a translated page
	 * resolved at runtime, even when the translation is what actually
	 * renders.
	 *
	 * @return string Empty string when the current request is not KKLPM-routed
	 *                or has no resolvable canonical.
	 */
	public static function get_canonical_url_for_request() {
		$source_page_id = (int) get_query_var( self::SOURCE_PAGE_ID_QUERY_VAR, 0 );

		if ( $source_page_id <= 0 ) {
			return '';
		}

		return self::get_canonical_url_for_page( $source_page_id );
	}

	/**
	 * Resolves the canonical URL for a page with active router mappings.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	public static function get_canonical_url_for_page( $page_id ) {
		$page_id = (int) $page_id;

		if ( $page_id <= 0 ) {
			return '';
		}

		$canonical_mapping = KKLPM_Domain_Map_Repository::get_canonical_mapping_for_page( $page_id );

		if ( is_array( $canonical_mapping ) ) {
			return self::build_mapping_url( $canonical_mapping );
		}

		$active_mappings = KKLPM_Domain_Map_Repository::get_active_mappings_for_page( $page_id );

		if ( empty( $active_mappings ) ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * Renders a canonical link tag for a page when a canonical URL exists.
	 *
	 * @param int $page_id Page ID.
	 * @return void
	 */
	public static function render_canonical_tag_for_page( $page_id ) {
		$canonical_url = self::get_canonical_url_for_page( $page_id );

		if ( '' === $canonical_url ) {
			return;
		}

		printf(
			'<link rel="canonical" href="%s" />' . "\n",
			esc_url( $canonical_url )
		);
	}

	/**
	 * Resolves the canonical URL for the isolated template branch, which
	 * skips `wp_head()` (and therefore the `get_canonical_url` filter
	 * integration) entirely and must print its own tag directly.
	 *
	 * Prefers the current routed request's mapped source page — correct
	 * even when a translation is what actually renders — and falls back to
	 * a direct lookup on the given page ID for requests that were not
	 * served through the Domain Router (e.g. a mapped landing page visited
	 * directly via its native permalink).
	 *
	 * @param int $fallback_page_id Page ID to use when the request isn't routed.
	 * @return string
	 */
	public static function get_canonical_url_for_current_page( $fallback_page_id ) {
		$canonical_url = self::get_canonical_url_for_request();

		if ( '' !== $canonical_url ) {
			return $canonical_url;
		}

		return self::get_canonical_url_for_page( $fallback_page_id );
	}

	/**
	 * Renders a canonical link tag for the current page request, preferring
	 * the routed request's mapped source page over the given fallback ID.
	 *
	 * Intended for the isolated landing page template branch — see
	 * get_canonical_url_for_current_page() for the resolution order.
	 *
	 * @param int $fallback_page_id Page ID to use when the request isn't routed.
	 * @return void
	 */
	public static function render_canonical_tag_for_current_page( $fallback_page_id ) {
		$canonical_url = self::get_canonical_url_for_current_page( $fallback_page_id );

		if ( '' === $canonical_url ) {
			return;
		}

		printf(
			'<link rel="canonical" href="%s" />' . "\n",
			esc_url( $canonical_url )
		);
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
		$candidates      = KKLPM_Domain_Map_Repository::find_request_candidates( $normalized_host, $normalized_path );

		return KKLPM_Domain_Router_Matcher::match_request(
			$normalized_host,
			$normalized_path,
			$candidates
		);
	}

	/**
	 * Whether a matched subpath mapping collides with existing WordPress content.
	 *
	 * Checks both a direct page-path lookup (works regardless of permalink
	 * structure) and `url_to_postid()` (also covers other public post types and
	 * archives, but only resolves when the site uses pretty permalinks).
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

		$existing_page    = get_page_by_path( $requested_path );
		$existing_post_id = $existing_page instanceof WP_Post ? (int) $existing_page->ID : 0;

		if ( ! $existing_post_id ) {
			$existing_post_id = url_to_postid( home_url( $normalized_path ) );
		}

		return 0 !== $existing_post_id && $existing_post_id !== $target_page_id;
	}

	/**
	 * Builds the absolute front-end URL represented by a mapping.
	 *
	 * Single shared source of truth for mapping-to-URL construction, used by
	 * both the runtime canonical resolver and the admin "Copy link" action, so
	 * the two never diverge on scheme-fallback behavior again.
	 *
	 * @param array $mapping Mapping data.
	 * @return string
	 */
	public static function build_mapping_url( array $mapping ) {
		$type  = isset( $mapping['type'] ) ? (string) $mapping['type'] : '';
		$value = isset( $mapping['value'] ) ? (string) $mapping['value'] : '';

		if ( '' === $value ) {
			return '';
		}

		if ( 'subpath' === $type ) {
			return home_url( $value );
		}

		$scheme = is_ssl() ? 'https' : wp_parse_url( home_url(), PHP_URL_SCHEME );

		if ( ! is_string( $scheme ) || '' === $scheme ) {
			$scheme = 'http';
		}

		return $scheme . '://' . $value . '/';
	}
}
