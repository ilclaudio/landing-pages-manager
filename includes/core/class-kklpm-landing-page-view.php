<?php
/**
 * Pure helpers for landing page view decisions.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides pure helpers that are easy to unit test.
 */
class KKLPM_Landing_Page_View {

	/**
	 * Normalizes the landing page enabled flag.
	 *
	 * @param mixed $submitted_value Submitted toggle value.
	 * @return string
	 */
	public static function normalize_enabled_value( $submitted_value ) {
		return empty( $submitted_value ) ? '0' : '1';
	}

	/**
	 * Resolves which template path should be used.
	 *
	 * @param bool   $is_page_request       Whether the current request is for a singular page.
	 * @param int    $post_id               Queried post ID.
	 * @param bool   $landing_enabled       Whether landing mode is enabled for the page.
	 * @param string $landing_template_path Plugin landing template path.
	 * @param bool   $template_exists       Whether the plugin template exists.
	 * @param string $fallback_template     Theme or fallback template path.
	 * @return string
	 */
	public static function resolve_template_path( $is_page_request, $post_id, $landing_enabled, $landing_template_path, $template_exists, $fallback_template ) {
		if ( ! $is_page_request || ! $post_id || ! $landing_enabled || ! $template_exists ) {
			return $fallback_template;
		}

		return $landing_template_path;
	}

	/**
	 * Resolves the frontend HTML to render.
	 *
	 * @param string $html_content      Stored HTML content.
	 * @param string $placeholder_html  Placeholder markup.
	 * @return string
	 */
	public static function resolve_html_content( $html_content, $placeholder_html ) {
		return '' === trim( (string) $html_content ) ? $placeholder_html : (string) $html_content;
	}
}
