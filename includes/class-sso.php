<?php
/**
 * SSO: ticket.ivynet.co.kr로의 단순 인증 토큰(HS256 JWT)을 생성한다.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class Sso {

	/** SSO 설정이 완료되어 있는지 — 시크릿과 URL이 모두 있어야 한다. */
	public static function is_configured(): bool {
		$s = Settings::get();
		return ! empty( $s['sso_secret'] ) && ! empty( $s['ticket_url'] );
	}

	/**
	 * 현재 로그인한 WP 사용자를 위한 HS256 JWT를 생성한다.
	 *
	 * @return string|\WP_Error
	 */
	public static function generate_token() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'not_logged_in', __( '로그인이 필요합니다.', 'ivy-support-ticket' ) );
		}

		$s      = Settings::get();
		$secret = (string) $s['sso_secret'];
		if ( empty( $secret ) ) {
			return new \WP_Error( 'no_sso_secret', __( 'SSO 시크릿이 설정되지 않았습니다.', 'ivy-support-ticket' ) );
		}

		$user = wp_get_current_user();
		$now  = time();

		$header  = self::b64url( (string) wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );
		$payload = self::b64url(
			(string) wp_json_encode(
				array(
					'sub'   => $user->user_email,
					'email' => $user->user_email,
					'name'  => $user->display_name,
					'iat'   => $now,
					'exp'   => $now + 300, // 5분
				)
			)
		);
		$sig = self::b64url( hash_hmac( 'sha256', "$header.$payload", $secret, true ) );

		return "$header.$payload.$sig";
	}

	/**
	 * ticket.ivynet.co.kr SSO 인증 URL을 반환한다.
	 *
	 * @param string $redirect 로그인 후 이동할 경로. /portal 등.
	 * @return string|\WP_Error
	 */
	public static function get_sso_url( string $redirect = '/portal' ) {
		$token = self::generate_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$s    = Settings::get();
		$base = rtrim( (string) $s['ticket_url'], '/' );
		$path = '/api/auth/sso?token=' . rawurlencode( $token ) . '&redirect=' . rawurlencode( $redirect );
		return $base . $path;
	}

	private static function b64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
