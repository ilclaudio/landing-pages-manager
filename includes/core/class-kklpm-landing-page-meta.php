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
	 * Theme header/footer toggle meta key.
	 *
	 * @var string
	 */
	const THEME_HEADER_FOOTER = '_kklpm_landing_theme_header_footer';

	/**
	 * WordPress head/footer assets toggle meta key.
	 *
	 * @var string
	 */
	const WP_ASSETS = '_kklpm_landing_wp_assets';

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
	 * Whether the theme header/footer should wrap the landing page.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_theme_header_footer_enabled( $post_id ) {
		return '1' === get_post_meta( $post_id, self::THEME_HEADER_FOOTER, true );
	}

	/**
	 * Whether WordPress head/footer assets should load on the landing page.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_wp_assets_enabled( $post_id ) {
		return '1' === get_post_meta( $post_id, self::WP_ASSETS, true );
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
