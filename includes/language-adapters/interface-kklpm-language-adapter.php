<?php
/**
 * Contract for multilingual plugin integrations.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stable internal boundary between KKLPM and any third-party translation plugin.
 */
interface KKLPM_Language_Adapter_Interface {

	/**
	 * Whether the underlying multilingual plugin is active.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Current language code (e.g. `it`, `en`), or null when not resolvable.
	 *
	 * @return string|null
	 */
	public function get_current_language();

	/**
	 * Language codes configured on the site.
	 *
	 * @return string[]
	 */
	public function get_available_languages();

	/**
	 * Resolves the translated page ID for a source page and target language.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code.
	 * @return int|null
	 */
	public function get_translated_page_id( $page_id, $lang );

	/**
	 * Resolves the default-language source page ID for a translated page.
	 *
	 * Inverse of get_translated_page_id(): given a page, returns the ID of
	 * the default-language page it is a translation of, or null when the
	 * page is not a translation (including when it already is the
	 * default-language page itself).
	 *
	 * @param int $page_id Page ID, possibly a translation.
	 * @return int|null
	 */
	public function get_source_page_id( $page_id );
}
