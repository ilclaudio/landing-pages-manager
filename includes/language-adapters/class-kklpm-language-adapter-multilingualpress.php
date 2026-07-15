<?php
/**
 * Language adapter for MultilingualPress.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps MultilingualPress behind the stable KKLPM language contract.
 *
 * MultilingualPress relates translated posts across WordPress multisite
 * sites rather than within a single site, so language codes are derived
 * from each related site's locale.
 */
class KKLPM_Language_Adapter_MultilingualPress implements KKLPM_Language_Adapter_Interface {

	/**
	 * Detects MultilingualPress without depending on its classes.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'MULTILINGUALPRESS_VERSION' );
	}

	/**
	 * Current language derived from the current site's locale.
	 *
	 * @return string|null
	 */
	public function get_current_language() {
		if ( ! $this->is_active() || ! function_exists( 'get_bloginfo' ) ) {
			return null;
		}

		return $this->short_lang_code( get_bloginfo( 'language' ) );
	}

	/**
	 * Available languages derived from every site in the network.
	 *
	 * @return string[]
	 */
	public function get_available_languages() {
		if ( ! $this->is_active() || ! function_exists( 'get_sites' ) ) {
			return array();
		}

		$languages = array();

		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
			$lang = $this->short_lang_code( $this->get_site_locale( (int) $site_id ) );

			if ( null !== $lang ) {
				$languages[] = $lang;
			}
		}

		return array_values( array_unique( $languages ) );
	}

	/**
	 * Translated page ID via `mlp_get_linked_elements()`, matched by site locale.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code.
	 * @return int|null
	 */
	public function get_translated_page_id( $page_id, $lang ) {
		if ( ! $this->is_active() || ! function_exists( 'mlp_get_linked_elements' ) ) {
			return null;
		}

		$relations = mlp_get_linked_elements( (int) $page_id );

		if ( ! is_array( $relations ) ) {
			return null;
		}

		foreach ( $relations as $site_id => $related_post_id ) {
			if ( $this->short_lang_code( $this->get_site_locale( (int) $site_id ) ) === strtolower( (string) $lang ) ) {
				return (int) $related_post_id;
			}
		}

		return null;
	}

	/**
	 * Reads a site's locale, falling back to the current site's language.
	 *
	 * @param int $site_id Site ID.
	 * @return string
	 */
	protected function get_site_locale( $site_id ) {
		$locale = function_exists( 'get_blog_option' ) ? get_blog_option( $site_id, 'WPLANG' ) : '';

		return is_string( $locale ) && '' !== $locale ? $locale : (string) get_bloginfo( 'language' );
	}

	/**
	 * Normalizes a WordPress locale string to a short language code.
	 *
	 * @param string $locale Locale string, e.g. `it_IT` or `it`.
	 * @return string|null
	 */
	protected function short_lang_code( $locale ) {
		if ( ! is_string( $locale ) || '' === $locale ) {
			return null;
		}

		return strtolower( substr( $locale, 0, 2 ) );
	}
}
