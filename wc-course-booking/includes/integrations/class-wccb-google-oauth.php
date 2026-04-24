<?php
/**
 * Google OAuth 2.0 — site-level connection used to push bookings to a
 * Google Calendar.
 *
 * Flow:
 *   1. Admin enters a Google Cloud OAuth client ID + secret on our settings page.
 *   2. Admin clicks 'Connect' → we send them to Google's consent screen.
 *   3. Google redirects to our callback (admin-post.php action) with a code.
 *   4. We exchange the code for access + refresh tokens and store them.
 *   5. Thereafter we silently refresh as needed to call the Calendar API.
 *
 * We intentionally avoid the google-api-php-client dependency and speak HTTP
 * directly via wp_remote_*.
 *
 * @package WC_Course_Booking
 */

defined( 'ABSPATH' ) || exit;

class WCCB_Google_OAuth {

	const SETTINGS_OPTION = 'wccb_google_settings';
	const TOKENS_OPTION   = 'wccb_google_tokens';
	const SCOPE           = 'https://www.googleapis.com/auth/calendar.events';
	const AUTH_URL        = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL       = 'https://oauth2.googleapis.com/token';

	public static function init() {
		add_action( 'admin_post_wccb_google_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_wccb_google_callback', array( __CLASS__, 'handle_callback' ) );
		add_action( 'admin_post_wccb_google_disconnect', array( __CLASS__, 'handle_disconnect' ) );
	}

	public static function get_settings() {
		$defaults = array(
			'client_id'     => '',
			'client_secret' => '',
			'calendar_id'   => 'primary',
			'send_invites'  => 1,
		);
		return wp_parse_args( get_option( self::SETTINGS_OPTION, array() ), $defaults );
	}

	public static function save_settings( array $data ) {
		$settings = self::get_settings();
		$settings['client_id']     = sanitize_text_field( $data['client_id'] ?? '' );
		$settings['client_secret'] = sanitize_text_field( $data['client_secret'] ?? '' );
		$settings['calendar_id']   = sanitize_text_field( $data['calendar_id'] ?? 'primary' );
		$settings['send_invites']  = empty( $data['send_invites'] ) ? 0 : 1;
		update_option( self::SETTINGS_OPTION, $settings );
	}

	public static function is_connected() {
		$tokens = get_option( self::TOKENS_OPTION, array() );
		return ! empty( $tokens['refresh_token'] );
	}

	public static function redirect_uri() {
		return admin_url( 'admin-post.php?action=wccb_google_callback' );
	}

	public static function handle_connect() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No permission.', 'wc-course-booking' ) );
		}
		check_admin_referer( 'wccb_google_connect' );

		$settings = self::get_settings();
		if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
			wp_safe_redirect( add_query_arg( 'wccb_status', 'missing-credentials', admin_url( 'admin.php?page=wccb-settings' ) ) );
			exit;
		}

		$state = wp_create_nonce( 'wccb_google_state_' . get_current_user_id() );
		set_transient( 'wccb_google_state_' . $state, get_current_user_id(), 10 * MINUTE_IN_SECONDS );

		$url = add_query_arg(
			array(
				'response_type' => 'code',
				'client_id'     => rawurlencode( $settings['client_id'] ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'scope'         => rawurlencode( self::SCOPE ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			),
			self::AUTH_URL
		);

		wp_redirect( $url );
		exit;
	}

	public static function handle_callback() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No permission.', 'wc-course-booking' ) );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		$expected_user = (int) get_transient( 'wccb_google_state_' . $state );
		delete_transient( 'wccb_google_state_' . $state );

		if ( ! $code || ! $expected_user || $expected_user !== get_current_user_id() ) {
			wp_safe_redirect( add_query_arg( 'wccb_status', 'oauth-failed', admin_url( 'admin.php?page=wccb-settings' ) ) );
			exit;
		}

		$settings = self::get_settings();
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			wp_safe_redirect( add_query_arg( 'wccb_status', 'token-exchange-failed', admin_url( 'admin.php?page=wccb-settings' ) ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			wp_safe_redirect( add_query_arg( 'wccb_status', 'token-exchange-failed', admin_url( 'admin.php?page=wccb-settings' ) ) );
			exit;
		}

		$tokens = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? ( get_option( self::TOKENS_OPTION )['refresh_token'] ?? '' ),
			'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ) - 60,
			'scope'         => $body['scope'] ?? self::SCOPE,
		);
		update_option( self::TOKENS_OPTION, $tokens );

		wp_safe_redirect( add_query_arg( 'wccb_status', 'connected', admin_url( 'admin.php?page=wccb-settings' ) ) );
		exit;
	}

	public static function handle_disconnect() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No permission.', 'wc-course-booking' ) );
		}
		check_admin_referer( 'wccb_google_disconnect' );
		delete_option( self::TOKENS_OPTION );
		wp_safe_redirect( add_query_arg( 'wccb_status', 'disconnected', admin_url( 'admin.php?page=wccb-settings' ) ) );
		exit;
	}

	/**
	 * Get a currently-valid access token, refreshing if necessary.
	 *
	 * @return string|WP_Error
	 */
	public static function get_access_token() {
		$tokens = get_option( self::TOKENS_OPTION, array() );
		if ( empty( $tokens['refresh_token'] ) ) {
			return new WP_Error( 'wccb_google_not_connected', __( 'Google Calendar is not connected.', 'wc-course-booking' ) );
		}

		if ( ! empty( $tokens['access_token'] ) && ! empty( $tokens['expires_at'] ) && time() < (int) $tokens['expires_at'] ) {
			return $tokens['access_token'];
		}

		$settings = self::get_settings();
		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'refresh_token' => $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'wccb_google_refresh_failed', wp_remote_retrieve_body( $response ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'wccb_google_refresh_failed', __( 'Missing access_token in refresh response.', 'wc-course-booking' ) );
		}

		$tokens['access_token'] = $body['access_token'];
		$tokens['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 ) - 60;
		if ( ! empty( $body['refresh_token'] ) ) {
			$tokens['refresh_token'] = $body['refresh_token'];
		}
		update_option( self::TOKENS_OPTION, $tokens );

		return $tokens['access_token'];
	}
}
