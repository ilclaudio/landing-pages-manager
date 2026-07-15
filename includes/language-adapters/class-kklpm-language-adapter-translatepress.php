<?php
/**
 * Language adapter for TranslatePress.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps TranslatePress behind the stable KKLPM language contract.
 *
 * TranslatePress translates a single physical page dynamically and does not
 * expose a separate translated post ID per language, so translated-page
 * resolution is an identity mapping here.
 */
class KKLPM_Language_Adapter_TranslatePress implements KKLPM_Language_Adapter_Interface {

	/**
	 * Detects TranslatePress without depending on its classes.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'TRP_PLUGIN_VERSION' );
	}

	/**
	 * Current language via TranslatePress' documented `$TRP_LANGUAGE` global.
	 *
	 * @return string|null
	 */
	public function get_current_language() {
		if ( ! $this->is_active() ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Third-party global name, defined by TranslatePress itself.
		global $TRP_LANGUAGE;

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Third-party global name, defined by TranslatePress itself.
		return is_string( $TRP_LANGUAGE ) && '' !== $TRP_LANGUAGE ? $TRP_LANGUAGE : null;
	}

	/**
	 * Available languages via the `trp_settings` option.
	 *
	 * @return string[]
	 */
	public function get_available_languages() {
		if ( ! $this->is_active() ) {
			return array();
		}

		$settings = get_option( 'trp_settings' );

		if ( ! is_array( $settings ) || empty( $settings['translation-languages'] ) || ! is_array( $settings['translation-languages'] ) ) {
			return array();
		}

		return array_values( $settings['translation-languages'] );
	}

	/**
	 * Identity resolution: TranslatePress serves the same physical page.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code (unused).
	 * @return int|null
	 */
	public function get_translated_page_id( $page_id, $lang ) {
		return $this->is_active() ? (int) $page_id : null;
	}
}
