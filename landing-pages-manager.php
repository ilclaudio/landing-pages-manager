<?php
/**
 * Plugin Name: KK Landing Pages Manager
 * Plugin URI:  https://github.com/ilclaudio/landing-pages-manager
 * Description: Manage landing pages with isolated templates, content grids, and domain routing.
 * Requires at least: 6.2
 * Version:     0.0.1
 * Author:      IoClaudio
 * Author URI:  https://www.claudiobattaglino.it
 * Domain Path: /languages
 * Text Domain: landing-pages-manager
 * License:     GPL-2.0-or-later
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

define( 'KKLPM_VERSION', '0.0.1' );
define( 'KKLPM_PLUGIN_FILE', __FILE__ );
define( 'KKLPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KKLPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once KKLPM_PLUGIN_DIR . 'includes/utils/class-kklpm-path-utils.php';
require_once KKLPM_PLUGIN_DIR . 'includes/core/class-kklpm-landing-page-meta.php';
require_once KKLPM_PLUGIN_DIR . 'includes/core/class-kklpm-landing-page-view.php';
require_once KKLPM_PLUGIN_DIR . 'includes/core/class-kklpm-landing-page-module.php';
require_once KKLPM_PLUGIN_DIR . 'includes/core/class-kklpm-plugin.php';
require_once KKLPM_PLUGIN_DIR . 'includes/domain-router/class-kklpm-domain-map-repository.php';
require_once KKLPM_PLUGIN_DIR . 'includes/domain-router/class-kklpm-domain-router-matcher.php';
require_once KKLPM_PLUGIN_DIR . 'includes/domain-router/class-kklpm-domain-router-module.php';
require_once KKLPM_PLUGIN_DIR . 'includes/domain-router/class-kklpm-domain-router-admin-page.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/interface-kklpm-language-adapter.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/class-kklpm-language-adapter-null.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/class-kklpm-language-adapter-wpml.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/class-kklpm-language-adapter-polylang.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/class-kklpm-language-adapter-translatepress.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/class-kklpm-language-adapter-weglot.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/class-kklpm-language-adapter-multilingualpress.php';
require_once KKLPM_PLUGIN_DIR . 'includes/language-adapters/class-kklpm-language-adapter-resolver.php';

register_activation_hook( __FILE__, array( 'KKLPM_Plugin', 'activate' ) );

KKLPM_Plugin::bootstrap();
