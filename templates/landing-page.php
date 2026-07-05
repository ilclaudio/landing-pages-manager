<?php
/**
 * Isolated landing page template.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

$post_id      = get_the_ID();
$html_content = KKLPM_Landing_Page_Meta::get_content( $post_id, KKLPM_Landing_Page_Meta::HTML );
$css_content  = KKLPM_Landing_Page_Meta::get_content( $post_id, KKLPM_Landing_Page_Meta::CSS );
$js_content   = KKLPM_Landing_Page_Meta::get_content( $post_id, KKLPM_Landing_Page_Meta::JS );

if ( '' === trim( $html_content ) ) {
	$html_content = sprintf(
		'<section class="kklpm-placeholder"><h1>%s</h1><p>%s</p></section>',
		esc_html__( 'Landing page ready', 'landing-pages-manager' ),
		esc_html__( 'Add custom HTML, CSS, and JavaScript in the Landing Page panel to start building this page.', 'landing-pages-manager' )
	);
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php if ( '' !== trim( $css_content ) ) : ?>
		<style>
			<?php echo $css_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSS is intentionally stored for users with unfiltered_html. ?>
		</style>
	<?php else : ?>
		<style>
			body {
				margin: 0;
				font-family: sans-serif;
				background: #f5f1e8;
				color: #1f1f1f;
			}

			.kklpm-placeholder {
				max-width: 48rem;
				margin: 12vh auto;
				padding: 3rem 1.5rem;
				border-radius: 1rem;
				background: #ffffff;
				box-shadow: 0 1.5rem 4rem rgba( 0, 0, 0, 0.08 );
			}
		</style>
	<?php endif; ?>
</head>
<body>
	<?php echo $html_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw HTML is intentionally stored for users with unfiltered_html. ?>
	<?php if ( '' !== trim( $js_content ) ) : ?>
		<script>
			<?php echo $js_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw JavaScript is intentionally stored for users with unfiltered_html. ?>
		</script>
	<?php endif; ?>
</body>
</html>
