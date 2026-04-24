<?php
/**
 * Admin settings page: Google Calendar credentials + connection state.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Admin_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_post_wccb_save_settings', array( __CLASS__, 'handle_save' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'wccb-bookings',
			__( 'Settings', 'wc-course-booking' ),
			__( 'Settings', 'wc-course-booking' ),
			'manage_woocommerce',
			'wccb-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No permission.', 'wc-course-booking' ) );
		}

		$settings   = WCCB_Google_OAuth::get_settings();
		$connected  = WCCB_Google_OAuth::is_connected();
		$status     = isset( $_GET['wccb_status'] ) ? sanitize_text_field( wp_unslash( $_GET['wccb_status'] ) ) : '';
		$redirect_uri = WCCB_Google_OAuth::redirect_uri();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Course Booking Settings', 'wc-course-booking' ); ?></h1>

			<?php if ( $status ) : ?>
				<div class="notice notice-<?php echo 'connected' === $status || 'disconnected' === $status ? 'success' : 'error'; ?>">
					<p><?php echo esc_html( self::status_message( $status ) ); ?></p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Google Calendar sync', 'wc-course-booking' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Paste a Google Cloud OAuth client’s ID & secret, then click Connect. Confirmed bookings will be pushed as events; cancelled bookings will be removed.', 'wc-course-booking' ); ?>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: redirect URI */
					esc_html__( 'Set this as the authorized redirect URI in Google Cloud: %s', 'wc-course-booking' ),
					'<code>' . esc_html( $redirect_uri ) . '</code>'
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wccb_save_settings' ); ?>
				<input type="hidden" name="action" value="wccb_save_settings" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wccb_google_client_id"><?php esc_html_e( 'Client ID', 'wc-course-booking' ); ?></label></th>
						<td><input type="text" id="wccb_google_client_id" name="client_id" class="regular-text" value="<?php echo esc_attr( $settings['client_id'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wccb_google_client_secret"><?php esc_html_e( 'Client Secret', 'wc-course-booking' ); ?></label></th>
						<td><input type="password" id="wccb_google_client_secret" name="client_secret" class="regular-text" value="<?php echo esc_attr( $settings['client_secret'] ); ?>" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wccb_google_calendar_id"><?php esc_html_e( 'Default calendar ID', 'wc-course-booking' ); ?></label></th>
						<td>
							<input type="text" id="wccb_google_calendar_id" name="calendar_id" class="regular-text" value="<?php echo esc_attr( $settings['calendar_id'] ); ?>" placeholder="primary" />
							<p class="description"><?php esc_html_e( 'Use "primary" for the connected account’s main calendar, or paste the ID of a shared calendar. Individual courses can override this.', 'wc-course-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email invites', 'wc-course-booking' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="send_invites" value="1" <?php checked( ! empty( $settings['send_invites'] ) ); ?> />
								<?php esc_html_e( 'Have Google send calendar invitations to the student and instructor.', 'wc-course-booking' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'wc-course-booking' ) ); ?>
			</form>

			<h3><?php esc_html_e( 'Connection', 'wc-course-booking' ); ?></h3>
			<?php if ( $connected ) : ?>
				<p>
					<span style="color:#1a7f37;">● <?php esc_html_e( 'Connected to Google Calendar.', 'wc-course-booking' ); ?></span>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<?php wp_nonce_field( 'wccb_google_disconnect' ); ?>
					<input type="hidden" name="action" value="wccb_google_disconnect" />
					<button class="button"><?php esc_html_e( 'Disconnect', 'wc-course-booking' ); ?></button>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Not connected.', 'wc-course-booking' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<?php wp_nonce_field( 'wccb_google_connect' ); ?>
					<input type="hidden" name="action" value="wccb_google_connect" />
					<button class="button button-primary" <?php disabled( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ); ?>>
						<?php esc_html_e( 'Connect Google Calendar', 'wc-course-booking' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function status_message( $status ) {
		switch ( $status ) {
			case 'connected':
				return __( 'Google Calendar connected successfully.', 'wc-course-booking' );
			case 'disconnected':
				return __( 'Google Calendar disconnected.', 'wc-course-booking' );
			case 'missing-credentials':
				return __( 'Enter a Client ID and Client Secret first.', 'wc-course-booking' );
			case 'oauth-failed':
				return __( 'OAuth handshake failed. Please try again.', 'wc-course-booking' );
			case 'token-exchange-failed':
				return __( 'Google refused the authorization code. Check your client credentials and redirect URI.', 'wc-course-booking' );
			case 'saved':
				return __( 'Settings saved.', 'wc-course-booking' );
		}
		return '';
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No permission.', 'wc-course-booking' ) );
		}
		check_admin_referer( 'wccb_save_settings' );

		WCCB_Google_OAuth::save_settings( wp_unslash( $_POST ) );
		wp_safe_redirect( add_query_arg( 'wccb_status', 'saved', admin_url( 'admin.php?page=wccb-settings' ) ) );
		exit;
	}
}
