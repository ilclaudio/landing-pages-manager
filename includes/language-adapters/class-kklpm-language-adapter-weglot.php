<?php
/**
 * Language adapter for Weglot.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps Weglot behind the stable KKLPM language contract.
 *
 * Weglot serves translated content for the same physical page/post through a
 * language-prefixed URL and does not expose a separate translated post ID,
 * so translated-page resolution is an identity mapping here.
 */
class KKLPM_Language_Adapter_Weglot implements KKLPM_Language_Adapter_Interface {

	/**
	 * Detects Weglot without depending on its classes.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'WEGLOT_VERSION' );
	}

	/**
	 * Current language via `weglot_get_current_language()`.
	 *
	 * @return string|null
	 */
	public function get_current_language() {
		if ( ! $this->is_active() || ! function_exists( 'weglot_get_current_language' ) ) {
			return null;
		}

		$lang = weglot_get_current_language();

		return is_string( $lang ) && '' !== $lang ? $lang : null;
	}

	/**
	 * Available languages via the `weglot_languages` option.
	 *
	 * @return string[]
	 */
	public function get_available_languages() {
		if ( ! $this->is_active() ) {
			return array();
		}

		$settings = get_option( 'weglot_languages' );

		if ( ! is_array( $settings ) || empty( $settings['destination_languages'] ) || ! is_array( $settings['destination_languages'] ) ) {
			return array();
		}

		return array_values( $settings['destination_languages'] );
	}

	/**
	 * Identity resolution: Weglot serves the same physical page.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code (unused).
	 * @return int|null
	 */
	public function get_translated_page_id( $page_id, $lang ) {
		return $this->is_active() ? (int) $page_id : null;
	}

	/**
	 * Identity model: there is no separate page to inherit from.
	 *
	 * Weglot serves the same physical page for every language, so a page is
	 * never "a translation of" a different page.
	 *
	 * @param int $page_id Page ID, possibly a translation.
	 * @return null
	 */
	public function get_source_page_id( $page_id ) {
		return null;
	}
}
