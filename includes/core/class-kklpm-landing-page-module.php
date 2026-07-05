<?php
/**
 * Landing page editor and frontend module.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles landing page admin UI and template loading.
 */
class KKLPM_Landing_Page_Module {

	/**
	 * Meta box nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'kklpm_save_landing_page_meta';

	/**
	 * Meta box nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'kklpm_landing_page_nonce';

	/**
	 * Registers module hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_page', array( $this, 'save_meta_box' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'filter_template_include' ) );
	}

	/**
	 * Registers the landing page meta box on pages.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'kklpm-landing-page-settings',
			__( 'Landing Page', 'landing-pages-manager' ),
			array( $this, 'render_meta_box' ),
			'page',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the landing page settings UI.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$enabled      = KKLPM_Landing_Page_Meta::is_enabled( $post->ID );
		$html_content = KKLPM_Landing_Page_Meta::get_content( $post->ID, KKLPM_Landing_Page_Meta::HTML );
		$css_content  = KKLPM_Landing_Page_Meta::get_content( $post->ID, KKLPM_Landing_Page_Meta::CSS );
		$js_content   = KKLPM_Landing_Page_Meta::get_content( $post->ID, KKLPM_Landing_Page_Meta::JS );
		$can_edit_raw = current_user_can( 'unfiltered_html' );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="kklpm-landing-enabled">
				<input
					type="checkbox"
					name="kklpm_landing_enabled"
					id="kklpm-landing-enabled"
					value="1"
					<?php checked( $enabled ); ?>
				/>
				<?php esc_html_e( 'Enable Landing Page', 'landing-pages-manager' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'When enabled, this page uses the plugin landing page template instead of the active theme template.', 'landing-pages-manager' ); ?>
		</p>
		<?php if ( ! $can_edit_raw ) : ?>
			<p>
				<?php esc_html_e( 'Custom HTML, CSS, and JavaScript fields are available only to users with the unfiltered_html capability.', 'landing-pages-manager' ); ?>
			</p>
		<?php endif; ?>
		<div id="kklpm-landing-fields" <?php echo $enabled ? '' : 'hidden'; ?>>
			<p>
				<label for="kklpm-landing-html"><strong><?php esc_html_e( 'HTML', 'landing-pages-manager' ); ?></strong></label>
			</p>
			<textarea
				id="kklpm-landing-html"
				name="kklpm_landing_html"
				rows="10"
				style="width:100%;"
				<?php disabled( ! $can_edit_raw ); ?>
			><?php echo esc_textarea( $html_content ); ?></textarea>
			<p>
				<label for="kklpm-landing-css"><strong><?php esc_html_e( 'CSS', 'landing-pages-manager' ); ?></strong></label>
			</p>
			<textarea
				id="kklpm-landing-css"
				name="kklpm_landing_css"
				rows="8"
				style="width:100%;"
				<?php disabled( ! $can_edit_raw ); ?>
			><?php echo esc_textarea( $css_content ); ?></textarea>
			<p>
				<label for="kklpm-landing-js"><strong><?php esc_html_e( 'JavaScript', 'landing-pages-manager' ); ?></strong></label>
			</p>
			<textarea
				id="kklpm-landing-js"
				name="kklpm_landing_js"
				rows="8"
				style="width:100%;"
				<?php disabled( ! $can_edit_raw ); ?>
			><?php echo esc_textarea( $js_content ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Disabling the landing page hides these fields in the editor, but keeps their saved content for later reuse.', 'landing-pages-manager' ); ?>
			</p>
		</div>
		<script>
			( function() {
				var toggle = document.getElementById( 'kklpm-landing-enabled' );
				var fields = document.getElementById( 'kklpm-landing-fields' );

				if ( ! toggle || ! fields ) {
					return;
				}

				function syncLandingFieldsVisibility() {
					fields.hidden = ! toggle.checked;
				}

				toggle.addEventListener( 'change', syncLandingFieldsVisibility );
				syncLandingFieldsVisibility();
			}() );
		</script>
		<?php
	}

	/**
	 * Saves landing page meta box values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( ! $this->can_save_meta_box( $post_id, $post ) ) {
			return;
		}

		$enabled_input = isset( $_POST['kklpm_landing_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['kklpm_landing_enabled'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in can_save_meta_box().
		$enabled       = KKLPM_Landing_Page_View::normalize_enabled_value(
			$enabled_input
		);
		update_post_meta( $post_id, KKLPM_Landing_Page_Meta::ENABLED, $enabled );

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return;
		}

		$this->update_raw_meta_field( $post_id, KKLPM_Landing_Page_Meta::HTML, 'kklpm_landing_html' );
		$this->update_raw_meta_field( $post_id, KKLPM_Landing_Page_Meta::CSS, 'kklpm_landing_css' );
		$this->update_raw_meta_field( $post_id, KKLPM_Landing_Page_Meta::JS, 'kklpm_landing_js' );
	}

	/**
	 * Replaces the page template when the landing mode is enabled.
	 *
	 * @param string $template Current template path.
	 * @return string
	 */
	public function filter_template_include( $template ) {
		$post_id          = get_queried_object_id();
		$landing_template = KKLPM_PLUGIN_DIR . 'templates/landing-page.php';
		$landing_enabled  = $post_id ? KKLPM_Landing_Page_Meta::is_enabled( $post_id ) : false;

		return KKLPM_Landing_Page_View::resolve_template_path(
			is_singular( 'page' ),
			$post_id,
			$landing_enabled,
			$landing_template,
			file_exists( $landing_template ),
			$template
		);
	}

	/**
	 * Whether the landing meta box data can be saved safely.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return bool
	 */
	protected function can_save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only.
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( 'page' !== $post->post_type ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Updates an unsanitized landing page content field.
	 *
	 * The project intentionally stores raw HTML/CSS/JS only for users with
	 * the unfiltered_html capability.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key to update.
	 * @param string $input_name Input field name.
	 * @return void
	 */
	protected function update_raw_meta_field( $post_id, $meta_key, $input_name ) {
		$value = isset( $_POST[ $input_name ] ) ? wp_unslash( $_POST[ $input_name ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in can_save_meta_box(); raw content is intentionally stored for users with unfiltered_html.
		update_post_meta( $post_id, $meta_key, $value );
	}
}
