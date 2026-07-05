<?php
/**
 * Landing page meta keys and helpers.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides a single source of truth for landing page meta keys.
 */
class KKLPM_Landing_Page_Meta {

	/**
	 * Toggle meta key.
	 *
	 * @var string
	 */
	const ENABLED = '_kklpm_landing_enabled';

	/**
	 * HTML content meta key.
	 *
	 * @var string
	 */
	const HTML = '_kklpm_landing_html';

	/**
	 * CSS content meta key.
	 *
	 * @var string
	 */
	const CSS = '_kklpm_landing_css';

	/**
	 * JavaScript content meta key.
	 *
	 * @var string
	 */
	const JS = '_kklpm_landing_js';

	/**
	 * Whether the landing page is enabled for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_enabled( $post_id ) {
		return '1' === get_post_meta( $post_id, self::ENABLED, true );
	}

	/**
	 * Returns a landing page content value.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $meta_key  Meta key to fetch.
	 * @return string
	 */
	public static function get_content( $post_id, $meta_key ) {
		return (string) get_post_meta( $post_id, $meta_key, true );
	}
}
