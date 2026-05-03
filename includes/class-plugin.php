<?php
/**
 * Plugin: 부트스트랩과 활성화·비활성화 훅 진입점.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/** plugins_loaded — 모든 훅을 한 곳에서 등록한다. */
	public static function boot(): void {
		load_plugin_textdomain( IVY_ST_TEXT_DOMAIN, false, dirname( IVY_ST_PLUGIN_BASENAME ) . '/languages' );

		AdminPages::register_hooks();
		UserMapping::register_hooks();
		Updater::init();

		// 플러그인 목록 화면에서 "설정" 빠른 링크 노출.
		add_filter( 'plugin_action_links_' . IVY_ST_PLUGIN_BASENAME, array( __CLASS__, 'add_settings_link' ) );
	}

	/** 활성화 훅 — v0.1.4부터 자동 시드를 두지 않는다. 사용자는 설정에서 명시적으로 등록한다. */
	public static function activate(): void {
		flush_rewrite_rules( false );
	}

	/** 비활성화 훅: 옵션 보존 (삭제는 uninstall.php가 담당). */
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function add_settings_link( array $links ): array {
		$url      = admin_url( 'admin.php?page=' . AdminPages::SLUG_SETTING );
		$settings = '<a href="' . esc_url( $url ) . '">' . esc_html__( '설정', 'ivy-support-ticket' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}
}
