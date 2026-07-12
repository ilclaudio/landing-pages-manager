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

	/**
	 * Whether the current theme should be treated as a block theme.
	 *
	 * @return bool
	 */
	public static function is_block_theme() {
		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

		if ( ! function_exists( 'apply_filters' ) ) {
			return $is_block_theme;
		}

		/**
		 * Filters whether the landing page should use block-theme template parts.
		 *
		 * @param bool $is_block_theme Whether the active theme is a block theme.
		 */
		return (bool) apply_filters( 'kklpm_is_block_theme', $is_block_theme );
	}

	/**
	 * Render the active theme header in a theme-compatible way.
	 *
	 * @return void
	 */
	public static function render_theme_header() {
		if ( self::is_block_theme() && function_exists( 'block_template_part' ) ) {
			echo '<header class="kklpm-theme-header">';
			block_template_part( 'header' );
			echo '</header>';
			return;
		}

		get_header();
	}

	/**
	 * Render the active theme footer in a theme-compatible way.
	 *
	 * @return void
	 */
	public static function render_theme_footer() {
		if ( self::is_block_theme() && function_exists( 'block_template_part' ) ) {
			echo '<footer class="kklpm-theme-footer">';
			block_template_part( 'footer' );
			echo '</footer>';
			return;
		}

		get_footer();
	}
}
