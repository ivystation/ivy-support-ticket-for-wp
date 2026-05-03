<?php
/**
 * Uninstall: 플러그인 제거 시 wp_options/user_meta 정리.
 *
 * `register_uninstall_hook`은 클래스 메서드를 안정적으로 부르지 못하는 환경이 있어
 * 워드프레스 코어가 권장하는 uninstall.php 방식을 사용한다.
 *
 * @package IvySupportTicket
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// 1) 플러그인 옵션
delete_option( 'ivy_st_settings' );
delete_site_option( 'ivy_st_settings' );

// 2) 사용자 매핑 캐시 (user_meta)
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s OR meta_key = %s",
		'ivy_st_pm_user_id',
		'ivy_st_pm_user_id_email'
	)
);

// 3) 디버그 로그 파일 — 베스트 에포트
$upload = wp_upload_dir();
if ( ! empty( $upload['basedir'] ) ) {
	$file = trailingslashit( $upload['basedir'] ) . 'ivy-support-ticket-debug.log';
	if ( file_exists( $file ) ) {
		@unlink( $file );
	}
}
