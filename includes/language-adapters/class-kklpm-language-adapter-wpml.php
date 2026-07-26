<?php
/**
 * Language adapter for WPML.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps WPML's filter-based API behind the stable KKLPM language contract.
 */
class KKLPM_Language_Adapter_WPML implements KKLPM_Language_Adapter_Interface {

	/**
	 * Detects WPML without depending on its classes.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * Current language via the `wpml_current_language` filter.
	 *
	 * @return string|null
	 */
	public function get_current_language() {
		if ( ! $this->is_active() || ! function_exists( 'apply_filters' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party filter defined by WPML itself.
		$lang = apply_filters( 'wpml_current_language', null );

		return is_string( $lang ) && '' !== $lang ? $lang : null;
	}

	/**
	 * Available languages via the `wpml_active_languages` filter.
	 *
	 * @return string[]
	 */
	public function get_available_languages() {
		if ( ! $this->is_active() || ! function_exists( 'apply_filters' ) ) {
			return array();
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party filter defined by WPML itself.
		$languages = apply_filters( 'wpml_active_languages', null );

		return is_array( $languages ) ? array_keys( $languages ) : array();
	}

	/**
	 * Translated page ID via the `wpml_object_id` filter.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code.
	 * @return int|null
	 */
	public function get_translated_page_id( $page_id, $lang ) {
		if ( ! $this->is_active() || ! function_exists( 'apply_filters' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party filter defined by WPML itself.
		$translated_id = apply_filters( 'wpml_object_id', (int) $page_id, 'page', false, (string) $lang );

		return is_numeric( $translated_id ) && (int) $translated_id > 0 ? (int) $translated_id : null;
	}

	/**
	 * Default-language source page ID via the `wpml_default_language` and
	 * `wpml_object_id` filters.
	 *
	 * @param int $page_id Page ID, possibly a translation.
	 * @return int|null
	 */
	public function get_source_page_id( $page_id ) {
		if ( ! $this->is_active() || ! function_exists( 'apply_filters' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party filter defined by WPML itself.
		$default_lang = apply_filters( 'wpml_default_language', null );

		if ( ! is_string( $default_lang ) || '' === $default_lang ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party filter defined by WPML itself.
		$source_id = apply_filters( 'wpml_object_id', (int) $page_id, 'page', false, $default_lang );

		if ( ! is_numeric( $source_id ) || (int) $source_id <= 0 || (int) $source_id === (int) $page_id ) {
			return null;
		}

		return (int) $source_id;
	}
}
