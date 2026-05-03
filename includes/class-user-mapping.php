<?php
/**
 * UserMapping: WP user ↔ pm User 매핑.
 * - allowed_user_ids 자동 등록 훅 (user_register / set_user_role)
 * - ensure_for_current_user(): 페이지 진입 시 한 번 pm User upsert 후 user_meta에 캐싱
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class UserMapping {

	/** Plugin::boot에서 호출되어 훅을 등록한다. */
	public static function register_hooks(): void {
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 1 );
		add_action( 'set_user_role', array( __CLASS__, 'on_set_user_role' ), 10, 3 );
	}

	/** 신규 가입 시 administrator/editor면 자동 등록 (옵션 활성 시). */
	public static function on_user_register( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		self::maybe_auto_enroll( $user );
	}

	/** 역할 변경 시 administrator/editor가 되면 자동 등록. */
	public static function on_set_user_role( int $user_id, string $new_role, $old_roles ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		self::maybe_auto_enroll( $user );
	}

	private static function maybe_auto_enroll( \WP_User $user ): void {
		$s = Settings::get();
		if ( empty( $s['auto_enroll_admin_editor'] ) ) {
			return;
		}
		$roles = (array) $user->roles;
		if ( ! array_intersect( $roles, array( 'administrator', 'editor' ) ) ) {
			return;
		}
		$ids = array_map( 'absint', (array) $s['allowed_user_ids'] );
		if ( in_array( $user->ID, $ids, true ) ) {
			return;
		}
		$ids[] = $user->ID;
		Settings::update( array( 'allowed_user_ids' => $ids ) );
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
