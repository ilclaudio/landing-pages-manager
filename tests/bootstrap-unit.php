<?php
/**
 * Unit tests bootstrap.
 *
 * @package LandingPageManager
 */

if ( ! defined( 'KKLPM_RUNNING_TESTS' ) ) {
	define( 'KKLPM_RUNNING_TESTS', true );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/utils/class-kklpm-path-utils.php';
require_once dirname( __DIR__ ) . '/includes/core/class-kklpm-landing-page-meta.php';
require_once dirname( __DIR__ ) . '/includes/core/class-kklpm-landing-page-view.php';
require_once dirname( __DIR__ ) . '/includes/domain-router/class-kklpm-domain-router-matcher.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/interface-kklpm-language-adapter.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/class-kklpm-language-adapter-null.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/class-kklpm-language-adapter-wpml.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/class-kklpm-language-adapter-polylang.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/class-kklpm-language-adapter-translatepress.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/class-kklpm-language-adapter-weglot.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/class-kklpm-language-adapter-multilingualpress.php';
require_once dirname( __DIR__ ) . '/includes/language-adapters/class-kklpm-language-adapter-resolver.php';
