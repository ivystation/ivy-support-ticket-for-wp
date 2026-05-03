<?php
/**
 * UserMapping: WP user ↔ pm User 매핑.
 *
 * v0.1.4부터는 자동 등록 훅을 두지 않는다. 사용자는 설정 → 사용자 매핑 탭에서
 * 검색·추가로 명시적으로 등록해야 한다. 본 클래스는 페이지 진입 시 한 번
 * pm User를 ensure하고 user_meta에 캐싱하는 책임만 가진다.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class UserMapping {

	/** Plugin::boot에서 호출되지만, 더 이상 등록할 훅이 없으므로 no-op. */
	public static function register_hooks(): void {
		// intentionally empty (v0.1.4)
	}

	/**
	 * 현재 로그인 사용자에 대해 pm User를 ensure 후 user_meta에 캐싱한다.
	 * 이메일이 변경된 경우 재호출. allowed가 아니면 동작하지 않음.
	 *
	 * @return string|\WP_Error  pm userId 또는 오류
	 */
	public static function ensure_for_current_user( ApiClient $api ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'ivy_st_not_logged_in', __( '로그인이 필요합니다.', 'ivy-support-ticket' ) );
		}
		$wp_user = wp_get_current_user();
		if ( ! Settings::user_is_allowed( $wp_user->ID ) ) {
			return new \WP_Error( 'ivy_st_not_allowed', __( '티켓 발행 권한이 없는 사용자입니다.', 'ivy-support-ticket' ) );
		}

		$cached = get_user_meta( $wp_user->ID, IVY_ST_USERMETA_PM_USER_ID, true );
		$cached_email = get_user_meta( $wp_user->ID, IVY_ST_USERMETA_PM_USER_ID . '_email', true );

		if ( ! empty( $cached ) && $cached_email === $wp_user->user_email ) {
			return (string) $cached;
		}

		$result = $api->ensure_user( $wp_user->user_email, $wp_user->display_name ?: $wp_user->user_login );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$pm_user_id = isset( $result['userId'] ) ? (string) $result['userId'] : '';
		if ( $pm_user_id === '' ) {
			return new \WP_Error( 'ivy_st_ensure_failed', __( '사용자 매핑에 실패했습니다.', 'ivy-support-ticket' ) );
		}

		update_user_meta( $wp_user->ID, IVY_ST_USERMETA_PM_USER_ID, $pm_user_id );
		update_user_meta( $wp_user->ID, IVY_ST_USERMETA_PM_USER_ID . '_email', $wp_user->user_email );
		return $pm_user_id;
	}
}
