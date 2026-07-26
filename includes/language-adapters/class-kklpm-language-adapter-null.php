<?php
/**
 * Fallback adapter used when no multilingual plugin is active.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * No-op adapter: keeps single-language behavior unchanged.
 */
class KKLPM_Language_Adapter_Null implements KKLPM_Language_Adapter_Interface {

	/**
	 * Always active: this is the guaranteed fallback.
	 *
	 * @return bool
	 */
	public function is_active() {
		return true;
	}

	/**
	 * No multilingual plugin means no distinct current language.
	 *
	 * @return string|null
	 */
	public function get_current_language() {
		return null;
	}

	/**
	 * No multilingual plugin means no configured language list.
	 *
	 * @return string[]
	 */
	public function get_available_languages() {
		return array();
	}

	/**
	 * Identity resolution: the source page is always the usable page.
	 *
	 * @param int    $page_id Source page ID.
	 * @param string $lang    Target language code (unused).
	 * @return int
	 */
	public function get_translated_page_id( $page_id, $lang ) {
		return (int) $page_id;
	}

	/**
	 * No multilingual plugin means no page is ever a translation of another.
	 *
	 * @param int $page_id Page ID, possibly a translation.
	 * @return null
	 */
	public function get_source_page_id( $page_id ) {
		return null;
	}
}
