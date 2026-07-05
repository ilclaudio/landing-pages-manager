<?php
/**
 * Unit tests bootstrap.
 *
 * @package LandingPageManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/utils/class-kklpm-path-utils.php';
require_once dirname( __DIR__ ) . '/includes/core/class-kklpm-landing-page-meta.php';
require_once dirname( __DIR__ ) . '/includes/core/class-kklpm-landing-page-view.php';
require_once dirname( __DIR__ ) . '/includes/domain-router/class-kklpm-domain-router-matcher.php';
