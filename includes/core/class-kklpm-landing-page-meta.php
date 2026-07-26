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

	/**
	 * Returns translation targets that are not ready to serve as landing pages.
	 *
	 * The source page remains the authoritative routing target; every translated
	 * page must opt into landing mode on its own before the router should use it.
	 *
	 * @param int $page_id Source page ID.
	 * @return array[]
	 */
	public static function get_translation_landing_gaps( $page_id ) {
		$page_id = (int) $page_id;

		if ( $page_id <= 0 || ! class_exists( 'KKLPM_Language_Adapter_Resolver' ) ) {
			return array();
		}

		$adapter   = KKLPM_Language_Adapter_Resolver::get_active_adapter();
		$languages = $adapter instanceof KKLPM_Language_Adapter_Interface ? $adapter->get_available_languages() : array();
		$gaps      = array();

		if ( empty( $languages ) || ! is_array( $languages ) ) {
			return array();
		}

		foreach ( $languages as $lang ) {
			$lang = is_string( $lang ) ? trim( $lang ) : '';

			if ( '' === $lang ) {
				continue;
			}

			$translated_id = $adapter->get_translated_page_id( $page_id, $lang );

			if ( ! is_numeric( $translated_id ) ) {
				continue;
			}

			$translated_id = (int) $translated_id;

			if ( $translated_id <= 0 || $translated_id === $page_id ) {
				continue;
			}

			$translated_page = get_post( $translated_id );

			if ( ! $translated_page instanceof WP_Post || 'page' !== $translated_page->post_type || 'trash' === $translated_page->post_status ) {
				$gaps[] = array(
					'lang'    => $lang,
					'page_id' => $translated_id,
					'reason'  => 'invalid',
					'title'   => '',
				);
				continue;
			}

			if ( self::is_enabled( $translated_id ) ) {
				continue;
			}

			$gaps[] = array(
				'lang'    => $lang,
				'page_id' => $translated_id,
				'reason'  => 'not_enabled',
				'title'   => get_the_title( $translated_id ),
			);
		}

		return $gaps;
	}
}
