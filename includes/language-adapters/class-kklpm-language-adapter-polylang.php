<?php
/**
 * Language adapter for Polylang.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps Polylang's global functions behind the stable KKLPM language contract.
 */
class KKLPM_Language_Adapter_Polylang implements KKLPM_Language_Adapter_Interface {

	/**
	 * Detects Polylang without depending on its classes.
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'pll_current_language' );
	}

	/**
	 * Prevents Polylang's own canonical redirect from fighting requests already
	 * routed by the Domain Router: synthetic mapped URLs never match Polylang's
	 * own permalink structure for the resolved page's language, which would
	 * otherwise 301 them to an unmapped path and 404.
	 *
	 * Called once per request by KKLPM_Language_Adapter_Resolver, only when
	 * this adapter is the active one.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pll_check_canonical_url', array( $this, 'maybe_skip_canonical_redirect' ) );
	}

	/**
	 * Skips Polylang's canonical redirect for requests already routed by the
	 * Domain Router; leaves it untouched for every other request.
	 *
	 * @param string|false $redirect_url Redirect URL Polylang wants to apply, or false.
	 * @return string|false
	 */
	public function maybe_skip_canonical_redirect( $redirect_url ) {
		global $wp;

		if ( isset( $wp->matched_rule ) && KKLPM_Domain_Router_Module::MATCHED_RULE === $wp->matched_rule ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Current language via `pll_current_language()`.
	 *
	 * @return string|null
	 */
	public function get_current_language() {
		if ( ! $this->is_active() ) {
			return null;
		}

		$lang = pll_current_language();

		return is_string( $lang ) && '' !== $lang ? $lang : null;
	}

	/**
	 * Available languages via `pll_languages_list()`.
	 *
	 * @return string[]
	 */
	public function get_available_languages() {
		if ( ! function_exists( 'pll_languages_list' ) ) {
			return array();
		}

		$languages = pll_languages_list();

		return is_array( $languages ) ? array_values( $languages ) : array();
	}

	/**
	 * Translated page ID via `pll_get_post()`.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code.
	 * @return int|null
	 */
	public function get_translated_page_id( $page_id, $lang ) {
		if ( ! function_exists( 'pll_get_post' ) ) {
			return null;
		}

		$translated_id = pll_get_post( (int) $page_id, (string) $lang );

		return is_numeric( $translated_id ) && (int) $translated_id > 0 ? (int) $translated_id : null;
	}

	/**
	 * Default-language source page ID via `pll_get_post()`.
	 *
	 * @param int $page_id Page ID, possibly a translation.
	 * @return int|null
	 */
	public function get_source_page_id( $page_id ) {
		if ( ! function_exists( 'pll_default_language' ) || ! function_exists( 'pll_get_post' ) ) {
			return null;
		}

		$default_language = pll_default_language();

		if ( ! is_string( $default_language ) || '' === $default_language ) {
			return null;
		}

		$source_id = pll_get_post( (int) $page_id, $default_language );

		if ( ! is_numeric( $source_id ) || (int) $source_id <= 0 || (int) $source_id === (int) $page_id ) {
			return null;
		}

		return (int) $source_id;
	}
}
