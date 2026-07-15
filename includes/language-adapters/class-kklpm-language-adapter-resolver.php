<?php
/**
 * Resolves the active multilingual plugin adapter.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Selects the first active adapter in priority order, with a Null fallback.
 */
class KKLPM_Language_Adapter_Resolver {

	/**
	 * Cached active adapter instance for the current request.
	 *
	 * @var KKLPM_Language_Adapter_Interface|null
	 */
	protected static $active_adapter = null;

	/**
	 * Returns the active multilingual adapter, falling back to the Null adapter.
	 *
	 * @return KKLPM_Language_Adapter_Interface
	 */
	public static function get_active_adapter() {
		if ( null !== self::$active_adapter ) {
			return self::$active_adapter;
		}

		foreach ( self::get_priority_order() as $adapter_class ) {
			if ( ! class_exists( $adapter_class ) ) {
				continue;
			}

			$adapter = new $adapter_class();

			if ( $adapter instanceof KKLPM_Language_Adapter_Interface && $adapter->is_active() ) {
				self::$active_adapter = $adapter;
				break;
			}
		}

		if ( null === self::$active_adapter ) {
			self::$active_adapter = new KKLPM_Language_Adapter_Null();
		}

		if ( method_exists( self::$active_adapter, 'register' ) ) {
			self::$active_adapter->register();
		}

		return self::$active_adapter;
	}

	/**
	 * Resets the cached adapter selection. Intended for test isolation.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$active_adapter = null;
	}

	/**
	 * Default adapter class priority order, filterable.
	 *
	 * @return string[]
	 */
	protected static function get_priority_order() {
		$default_order = array(
			'KKLPM_Language_Adapter_WPML',
			'KKLPM_Language_Adapter_Polylang',
			'KKLPM_Language_Adapter_TranslatePress',
			'KKLPM_Language_Adapter_Weglot',
			'KKLPM_Language_Adapter_MultilingualPress',
			'KKLPM_Language_Adapter_Null',
		);

		if ( ! function_exists( 'apply_filters' ) ) {
			return $default_order;
		}

		/**
		 * Filters the adapter class priority order.
		 *
		 * The first adapter whose `is_active()` returns true wins.
		 *
		 * @param string[] $default_order Adapter class names in priority order.
		 */
		return apply_filters( 'kklpm_language_adapter_priority', $default_order );
	}
}
