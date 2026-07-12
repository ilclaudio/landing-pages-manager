<?php
/**
 * Admin CRUD page for domain router mappings.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders and processes the Domain Router admin UI.
 */
class KKLPM_Domain_Router_Admin_Page {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'kklpm-domain-router';

	/**
	 * Capability required to manage mappings.
	 *
	 * @var string
	 */
	const CAPABILITY = 'kklpm_manage_domain_router';

	/**
	 * Save action nonce name.
	 *
	 * @var string
	 */
	const SAVE_NONCE_NAME = 'kklpm_domain_router_save_nonce';

	/**
	 * Save action nonce action.
	 *
	 * @var string
	 */
	const SAVE_NONCE_ACTION = 'kklpm_domain_router_save_mapping';

	/**
	 * Row action nonce query arg name.
	 *
	 * @var string
	 */
	const ACTION_NONCE_NAME = 'kklpm_domain_router_action_nonce';

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_kklpm_domain_router_save_mapping', array( $this, 'handle_save_action' ) );
		add_action( 'admin_post_kklpm_domain_router_delete_mapping', array( $this, 'handle_delete_action' ) );
		add_action( 'admin_post_kklpm_domain_router_toggle_mapping', array( $this, 'handle_toggle_action' ) );
	}

	/**
	 * Registers the plugin settings page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Landing Domain Router', 'landing-pages-manager' ),
			__( 'Landing Domain Router', 'landing-pages-manager' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the mapping management page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage domain router mappings.', 'landing-pages-manager' ) );
		}

		$edit_mapping = $this->get_current_edit_mapping();
		$mappings     = KKLPM_Domain_Map_Repository::get_all_mappings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Landing Domain Router', 'landing-pages-manager' ); ?></h1>
			<?php $this->render_notice(); ?>

			<h2><?php echo $edit_mapping ? esc_html__( 'Edit Mapping', 'landing-pages-manager' ) : esc_html__( 'Add Mapping', 'landing-pages-manager' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kklpm_domain_router_save_mapping" />
				<input type="hidden" name="mapping_id" value="<?php echo $edit_mapping ? esc_attr( (string) $edit_mapping['id'] ) : '0'; ?>" />
				<?php wp_nonce_field( self::SAVE_NONCE_ACTION, self::SAVE_NONCE_NAME ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="kklpm-domain-type"><?php esc_html_e( 'Type', 'landing-pages-manager' ); ?></label></th>
							<td>
								<select name="mapping[type]" id="kklpm-domain-type">
									<?php foreach ( KKLPM_Domain_Map_Repository::get_allowed_types() as $type ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $edit_mapping ? $edit_mapping['type'] : 'subdomain', $type ); ?>>
											<?php echo esc_html( ucfirst( $type ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="kklpm-domain-value"><?php esc_html_e( 'Value', 'landing-pages-manager' ); ?></label></th>
							<td>
								<input
									type="text"
									class="regular-text"
									name="mapping[value]"
									id="kklpm-domain-value"
									value="<?php echo esc_attr( $edit_mapping ? $edit_mapping['value'] : '' ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Use a host for subdomain/external mappings or a path for subpath mappings.', 'landing-pages-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="kklpm-domain-page"><?php esc_html_e( 'Landing Page', 'landing-pages-manager' ); ?></label></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'             => 'mapping[page_id]',
										'id'               => 'kklpm-domain-page',
										'show_option_none' => esc_html__( 'Select a page', 'landing-pages-manager' ),
										'option_none_value' => '0',
										'selected'         => $edit_mapping ? (int) $edit_mapping['page_id'] : 0,
										'post_status'      => array( 'publish', 'draft', 'private' ),
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="kklpm-domain-lang"><?php esc_html_e( 'Language', 'landing-pages-manager' ); ?></label></th>
							<td>
								<input
									type="text"
									class="regular-text"
									name="mapping[lang]"
									id="kklpm-domain-lang"
									value="<?php echo esc_attr( $edit_mapping ? (string) $edit_mapping['lang'] : '' ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Optional placeholder for Step 3 multilingual routing.', 'landing-pages-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Active', 'landing-pages-manager' ); ?></th>
							<td>
								<label for="kklpm-domain-active">
									<input
										type="checkbox"
										name="mapping[active]"
										id="kklpm-domain-active"
										value="1"
										<?php checked( ! $edit_mapping || ! empty( $edit_mapping['active'] ) ); ?>
									/>
									<?php esc_html_e( 'Enable this mapping', 'landing-pages-manager' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( $edit_mapping ? __( 'Update Mapping', 'landing-pages-manager' ) : __( 'Add Mapping', 'landing-pages-manager' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Existing Mappings', 'landing-pages-manager' ); ?></h2>
			<?php if ( empty( $mappings ) ) : ?>
				<p><?php esc_html_e( 'No mappings configured yet.', 'landing-pages-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'landing-pages-manager' ); ?></th>
							<th><?php esc_html_e( 'Type', 'landing-pages-manager' ); ?></th>
							<th><?php esc_html_e( 'Value', 'landing-pages-manager' ); ?></th>
							<th><?php esc_html_e( 'Page', 'landing-pages-manager' ); ?></th>
							<th><?php esc_html_e( 'Language', 'landing-pages-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', 'landing-pages-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'landing-pages-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $mappings as $mapping ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $mapping['id'] ); ?></td>
								<td><?php echo esc_html( (string) $mapping['type'] ); ?></td>
								<td><code><?php echo esc_html( (string) $mapping['value'] ); ?></code></td>
								<td><?php echo esc_html( $this->get_page_label( (int) $mapping['page_id'] ) ); ?></td>
								<td><?php echo esc_html( (string) $mapping['lang'] ); ?></td>
								<td><?php echo ! empty( $mapping['active'] ) ? esc_html__( 'Active', 'landing-pages-manager' ) : esc_html__( 'Inactive', 'landing-pages-manager' ); ?></td>
								<td>
									<a href="<?php echo esc_url( self::get_page_url( array( 'edit' => (int) $mapping['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'landing-pages-manager' ); ?></a>
									|
									<a href="<?php echo esc_url( $this->get_row_action_url( 'kklpm_domain_router_toggle_mapping', (int) $mapping['id'] ) ); ?>">
										<?php echo ! empty( $mapping['active'] ) ? esc_html__( 'Disable', 'landing-pages-manager' ) : esc_html__( 'Enable', 'landing-pages-manager' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( $this->get_row_action_url( 'kklpm_domain_router_delete_mapping', (int) $mapping['id'] ) ); ?>">
										<?php esc_html_e( 'Delete', 'landing-pages-manager' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles create/update submissions.
	 *
	 * @return void
	 */
	public function handle_save_action() {
		$this->assert_manage_capability();
		$this->verify_save_nonce();

		$mapping_id = isset( $_POST['mapping_id'] ) ? absint( wp_unslash( $_POST['mapping_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$raw_input  = isset( $_POST['mapping'] ) && is_array( $_POST['mapping'] ) ? (array) wp_unslash( $_POST['mapping'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; field sanitization happens immediately in sanitize_mapping_input().
		$data       = $this->sanitize_mapping_input( $raw_input );
		$success    = $mapping_id > 0
			? KKLPM_Domain_Map_Repository::update_mapping( $mapping_id, $data )
			: KKLPM_Domain_Map_Repository::insert_mapping( $data );

		$this->redirect_with_notice(
			$success ? ( $mapping_id > 0 ? 'updated' : 'created' ) : 'invalid'
		);
	}

	/**
	 * Handles delete requests.
	 *
	 * @return void
	 */
	public function handle_delete_action() {
		$this->assert_manage_capability();
		$mapping_id = $this->verify_row_action_request();

		$deleted = $mapping_id > 0 ? KKLPM_Domain_Map_Repository::delete_mapping( $mapping_id ) : false;

		$this->redirect_with_notice( $deleted ? 'deleted' : 'invalid' );
	}

	/**
	 * Handles active/inactive toggle requests.
	 *
	 * @return void
	 */
	public function handle_toggle_action() {
		$this->assert_manage_capability();
		$mapping_id = $this->verify_row_action_request();
		$mapping    = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		if ( ! is_array( $mapping ) ) {
			$this->redirect_with_notice( 'invalid' );
			return;
		}

		$updated = KKLPM_Domain_Map_Repository::update_mapping(
			$mapping_id,
			array(
				'type'    => $mapping['type'],
				'value'   => $mapping['value'],
				'page_id' => $mapping['page_id'],
				'active'  => empty( $mapping['active'] ) ? 1 : 0,
				'lang'    => $mapping['lang'],
			)
		);

		$this->redirect_with_notice( $updated ? 'toggled' : 'invalid' );
	}

	/**
	 * Sanitizes mapping input coming from the admin form.
	 *
	 * @param array $input Raw input array.
	 * @return array
	 */
	public function sanitize_mapping_input( array $input ) {
		return array(
			'type'    => isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '',
			'value'   => isset( $input['value'] ) ? sanitize_text_field( (string) $input['value'] ) : '',
			'page_id' => isset( $input['page_id'] ) ? (int) $input['page_id'] : 0,
			'active'  => empty( $input['active'] ) ? 0 : 1,
			'lang'    => isset( $input['lang'] ) ? sanitize_text_field( (string) $input['lang'] ) : '',
		);
	}

	/**
	 * Renders a settings-page notice based on the last action result.
	 *
	 * @return void
	 */
	protected function render_notice() {
		$notice = isset( $_GET['kklpm_notice'] ) ? sanitize_key( wp_unslash( $_GET['kklpm_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query arg.

		if ( '' === $notice ) {
			return;
		}

		$messages = array(
			'created' => __( 'Mapping created.', 'landing-pages-manager' ),
			'updated' => __( 'Mapping updated.', 'landing-pages-manager' ),
			'toggled' => __( 'Mapping status updated.', 'landing-pages-manager' ),
			'deleted' => __( 'Mapping deleted.', 'landing-pages-manager' ),
			'invalid' => __( 'Unable to save the mapping. Check the submitted values.', 'landing-pages-manager' ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		$notice_class = 'invalid' === $notice ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $notice_class ); ?>"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div>
		<?php
	}

	/**
	 * Returns the mapping being edited, if any.
	 *
	 * @return array|null
	 */
	protected function get_current_edit_mapping() {
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query arg.

		if ( $edit_id <= 0 ) {
			return null;
		}

		return KKLPM_Domain_Map_Repository::get_mapping( $edit_id );
	}

	/**
	 * Returns a human-readable label for the mapped page.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	protected function get_page_label( $page_id ) {
		$page = $page_id > 0 ? get_post( $page_id ) : null;

		if ( ! $page instanceof WP_Post ) {
			return __( 'Missing page', 'landing-pages-manager' );
		}

		return sprintf(
			/* translators: 1: page title, 2: page ID. */
			__( '%1$s (#%2$d)', 'landing-pages-manager' ),
			$page->post_title,
			$page->ID
		);
	}

	/**
	 * Builds a row-action URL with nonce protection.
	 *
	 * @param string $action    Admin-post action.
	 * @param int    $mapping_id Mapping ID.
	 * @return string
	 */
	protected function get_row_action_url( $action, $mapping_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'     => $action,
					'mapping_id' => $mapping_id,
				),
				admin_url( 'admin-post.php' )
			),
			$action . ':' . $mapping_id,
			self::ACTION_NONCE_NAME
		);
	}

	/**
	 * Redirects back to the settings page with a notice.
	 *
	 * @param string $notice Notice slug.
	 * @return void
	 */
	protected function redirect_with_notice( $notice ) {
		$redirect_url = self::get_page_url(
			array(
				'kklpm_notice' => sanitize_key( $notice ),
			)
		);

		// WP_UnitTestCase bootstraps can emit output before admin-post handlers run,
		// so skip the redirect when headers are no longer writable.
		if ( headers_sent() ) {
			return;
		}

		wp_safe_redirect( $redirect_url );
	}

	/**
	 * Builds the settings page URL with optional query arguments.
	 *
	 * @param array $args Optional query arguments.
	 * @return string
	 */
	public static function get_page_url( array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE_SLUG,
				),
				$args
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Ensures the current user can manage router mappings.
	 *
	 * @return void
	 */
	protected function assert_manage_capability() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage domain router mappings.', 'landing-pages-manager' ) );
		}
	}

	/**
	 * Verifies the save form nonce.
	 *
	 * @return void
	 */
	protected function verify_save_nonce() {
		$nonce = isset( $_POST[ self::SAVE_NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::SAVE_NONCE_NAME ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification method.

		if ( ! wp_verify_nonce( $nonce, self::SAVE_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid save request.', 'landing-pages-manager' ) );
		}
	}

	/**
	 * Verifies a nonce-protected row action request and returns the mapping ID.
	 *
	 * @return int
	 */
	protected function verify_row_action_request() {
		$mapping_id = isset( $_REQUEST['mapping_id'] ) ? absint( wp_unslash( $_REQUEST['mapping_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verification performed below.
		$nonce      = isset( $_REQUEST[ self::ACTION_NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::ACTION_NONCE_NAME ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verification method.
		$action     = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verification method.

		if ( ! wp_verify_nonce( $nonce, $action . ':' . $mapping_id ) ) {
			wp_die( esc_html__( 'Invalid action request.', 'landing-pages-manager' ) );
		}

		return $mapping_id;
	}
}
