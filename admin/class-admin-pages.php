<?php
/**
 * AdminPages: 사이드바 메뉴, Adminbar 항목, 설정 페이지 렌더링, AJAX 핸들러 등록.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class AdminPages {

	const MENU_SLUG    = 'ivy-st-list';
	const SLUG_NEW     = 'ivy-st-new';
	const SLUG_LIST    = 'ivy-st-list';
	const SLUG_SHOW    = 'ivy-st-show';
	const SLUG_SETTING = 'ivy-st-settings';
	const NONCE_AJAX   = 'ivy_st_ajax';

	public static function register_hooks(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar' ), 80 );
		add_action( 'admin_post_ivy_st_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'wp_ajax_ivy_st_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/** 사이드바: Support Ticket → 새 티켓 작성 / 티켓 목록 / 설정. */
	public static function register_menu(): void {
		$can_view = self::current_user_can_use();

		// 최상위는 모든 STAFF 운영자에게 보이지만, 메뉴 캡은 manage_options(설정 권한자) 또는 등록 사용자.
		add_menu_page(
			__( 'Support Ticket', 'ivy-support-ticket' ),
			__( 'Support Ticket', 'ivy-support-ticket' ),
			$can_view ? 'read' : 'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_list' ),
			'dashicons-tickets-alt',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( '티켓 목록', 'ivy-support-ticket' ),
			__( '티켓 목록', 'ivy-support-ticket' ),
			$can_view ? 'read' : 'manage_options',
			self::SLUG_LIST,
			array( __CLASS__, 'render_list' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( '새 티켓 작성', 'ivy-support-ticket' ),
			__( '새 티켓 작성', 'ivy-support-ticket' ),
			$can_view ? 'read' : 'manage_options',
			self::SLUG_NEW,
			array( __CLASS__, 'render_new' )
		);

		// 상세는 메뉴 노출 없이 라우트만 등록 (목록에서 링크 클릭 진입).
		add_submenu_page(
			null,
			__( '티켓 상세', 'ivy-support-ticket' ),
			'',
			$can_view ? 'read' : 'manage_options',
			self::SLUG_SHOW,
			array( __CLASS__, 'render_show' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( '설정', 'ivy-support-ticket' ),
			__( '설정', 'ivy-support-ticket' ),
			'manage_options',
			self::SLUG_SETTING,
			array( __CLASS__, 'render_settings' )
		);
	}

	/** 상단 Adminbar 항목 (모든 페이지에서 빠른 진입). */
	public static function register_admin_bar( \WP_Admin_Bar $bar ): void {
		if ( ! self::current_user_can_use() && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'ivy-support-ticket',
				'title' => '<span class="ab-icon dashicons dashicons-tickets-alt" style="margin-top:4px"></span>' . esc_html__( 'Support Ticket', 'ivy-support-ticket' ),
				'href'  => admin_url( 'admin.php?page=' . self::SLUG_LIST ),
				'meta'  => array( 'title' => __( '티켓 관리', 'ivy-support-ticket' ) ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'ivy-support-ticket',
				'id'     => 'ivy-st-new',
				'title'  => __( '새 티켓 작성', 'ivy-support-ticket' ),
				'href'   => admin_url( 'admin.php?page=' . self::SLUG_NEW ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'ivy-support-ticket',
				'id'     => 'ivy-st-list',
				'title'  => __( '티켓 목록', 'ivy-support-ticket' ),
				'href'   => admin_url( 'admin.php?page=' . self::SLUG_LIST ),
			)
		);
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node(
				array(
					'parent' => 'ivy-support-ticket',
					'id'     => 'ivy-st-settings',
					'title'  => __( '설정', 'ivy-support-ticket' ),
					'href'   => admin_url( 'admin.php?page=' . self::SLUG_SETTING ),
				)
			);
		}
	}

	/** 현재 user가 티켓 페이지에 접근할 수 있는지. */
	public static function current_user_can_use(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return Settings::user_is_allowed( get_current_user_id() ) || current_user_can( 'manage_options' );
	}

	public static function enqueue_assets( string $hook ): void {
		if ( strpos( (string) $hook, 'ivy-st-' ) === false && strpos( (string) $hook, self::MENU_SLUG ) === false ) {
			// 본 플러그인 페이지가 아니면 자산을 부르지 않는다.
			return;
		}
		wp_enqueue_style(
			'ivy-st-admin',
			IVY_ST_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			IVY_ST_VERSION
		);
		wp_enqueue_script(
			'ivy-st-settings',
			IVY_ST_PLUGIN_URL . 'assets/js/settings.js',
			array( 'jquery' ),
			IVY_ST_VERSION,
			true
		);
		wp_localize_script(
			'ivy-st-settings',
			'IVY_ST',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_AJAX ),
				'i18n'    => array(
					'testing' => __( '연결 확인 중...', 'ivy-support-ticket' ),
					'success' => __( '연결 성공', 'ivy-support-ticket' ),
					'failed'  => __( '연결 실패', 'ivy-support-ticket' ),
				),
			)
		);
	}

	public static function render_list(): void {
		if ( ! self::current_user_can_use() ) {
			wp_die( esc_html__( '이 페이지에 접근할 권한이 없습니다.', 'ivy-support-ticket' ) );
		}
		require IVY_ST_PLUGIN_DIR . 'admin/views/ticket-list.php';
	}

	public static function render_new(): void {
		if ( ! self::current_user_can_use() ) {
			wp_die( esc_html__( '이 페이지에 접근할 권한이 없습니다.', 'ivy-support-ticket' ) );
		}
		require IVY_ST_PLUGIN_DIR . 'admin/views/ticket-new.php';
	}

	public static function render_show(): void {
		if ( ! self::current_user_can_use() ) {
			wp_die( esc_html__( '이 페이지에 접근할 권한이 없습니다.', 'ivy-support-ticket' ) );
		}
		require IVY_ST_PLUGIN_DIR . 'admin/views/ticket-show.php';
	}

	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '이 페이지에 접근할 권한이 없습니다.', 'ivy-support-ticket' ) );
		}
		require IVY_ST_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/** 설정 폼 제출 — admin-post 핸들러. */
	public static function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '권한이 없습니다.', 'ivy-support-ticket' ) );
		}
		check_admin_referer( 'ivy_st_save_settings' );

		$patch = array(
			'api_base'                 => isset( $_POST['api_base'] ) ? wp_unslash( $_POST['api_base'] ) : '',
			'allowed_user_ids'         => isset( $_POST['allowed_user_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['allowed_user_ids'] ) ) : array(),
			'auto_enroll_admin_editor' => ! empty( $_POST['auto_enroll_admin_editor'] ),
			'debug'                    => ! empty( $_POST['debug'] ),
		);
		// API Key는 입력 시에만 갱신, 빈 값이면 기존 유지.
		if ( isset( $_POST['api_key'] ) ) {
			$patch['api_key'] = wp_unslash( $_POST['api_key'] );
		}

		Settings::update( $patch );

		// "기본값으로 재설정" 버튼은 별도 액션.
		if ( isset( $_POST['ivy_st_reset_to_defaults'] ) ) {
			Settings::update(
				array( 'allowed_user_ids' => Settings::collect_admin_editor_user_ids() )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG_SETTING,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** "연결 테스트" AJAX 핸들러. */
	public static function ajax_test_connection(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}
		$client = new ApiClient();
		$result = $client->health();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}
		wp_send_json_success( $result );
	}
}
