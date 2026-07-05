<?php
/**
 * Main plugin bootstrap class.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates module bootstrapping for the plugin.
 */
class KKLPM_Plugin {

	/**
	 * Plugin modules.
	 *
	 * @var array
	 */
	protected $modules = array();

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		$plugin = new self();
		$plugin->register_modules();
		$plugin->register_hooks();
	}

	/**
	 * Registers plugin modules.
	 *
	 * @return void
	 */
	protected function register_modules() {
		$this->modules[] = new KKLPM_Landing_Page_Module();
	}

	/**
	 * Registers hooks for all modules.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
		}
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'landing-pages-manager',
			false,
			dirname( plugin_basename( KKLPM_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
