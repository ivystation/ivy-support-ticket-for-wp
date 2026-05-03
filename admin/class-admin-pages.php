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
		add_action( 'wp_ajax_ivy_st_create_ticket', array( __CLASS__, 'ajax_create_ticket' ) );
		add_action( 'wp_ajax_ivy_st_add_comment', array( __CLASS__, 'ajax_add_comment' ) );
		add_action( 'wp_ajax_ivy_st_presign', array( __CLASS__, 'ajax_presign' ) );
		add_action( 'wp_ajax_ivy_st_presign_get', array( __CLASS__, 'ajax_presign_get' ) );
		add_action( 'wp_ajax_ivy_st_user_search', array( __CLASS__, 'ajax_user_search' ) );
		add_action( 'wp_ajax_ivy_st_user_add', array( __CLASS__, 'ajax_user_add' ) );
		add_action( 'wp_ajax_ivy_st_user_remove', array( __CLASS__, 'ajax_user_remove' ) );
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
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// 본 플러그인 페이지가 아니면 자산을 부르지 않는다 — page 쿼리 파라미터로 식별.
		$is_plugin_page = in_array(
			$page,
			array( self::SLUG_LIST, self::SLUG_NEW, self::SLUG_SHOW, self::SLUG_SETTING ),
			true
		);
		if ( ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style(
			'ivy-st-admin',
			IVY_ST_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			IVY_ST_VERSION
		);

		$shared = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_AJAX ),
			'i18n'    => array(
				'testing'         => __( '연결 확인 중...', 'ivy-support-ticket' ),
				'success'         => __( '연결 성공', 'ivy-support-ticket' ),
				'failed'          => __( '연결 실패', 'ivy-support-ticket' ),
				'submitting'      => __( '제출 중...', 'ivy-support-ticket' ),
				'created'         => __( '티켓이 발행되었습니다.', 'ivy-support-ticket' ),
				'commenting'      => __( '댓글 등록 중...', 'ivy-support-ticket' ),
				'commentDone'     => __( '댓글이 등록되었습니다.', 'ivy-support-ticket' ),
				'genericError'    => __( '요청에 실패했습니다.', 'ivy-support-ticket' ),
				'attachUploading' => __( '업로드 중...', 'ivy-support-ticket' ),
				'attachDone'      => __( '완료', 'ivy-support-ticket' ),
				'attachTooLarge'  => __( '파일이 10MB를 초과합니다.', 'ivy-support-ticket' ),
				'attachBadType'   => __( '허용되지 않는 파일 형식입니다.', 'ivy-support-ticket' ),
				'attachTooMany'   => __( '첨부는 최대 ${n}개입니다.', 'ivy-support-ticket' ),
				'attachR2Cors'    => __( 'R2 업로드 실패. 사이트 도메인의 R2 CORS 등록을 확인하세요.', 'ivy-support-ticket' ),
				'downloading'     => __( '준비 중...', 'ivy-support-ticket' ),
			),
		);

		if ( $page === self::SLUG_SETTING ) {
			// 단일 settings.js로 통합 (v0.1.3) — 연결 테스트 + 사용자 매핑(검색·추가·해지)
			wp_enqueue_script(
				'ivy-st-settings',
				IVY_ST_PLUGIN_URL . 'assets/js/settings.js',
				array( 'jquery' ),
				IVY_ST_VERSION,
				true
			);
			wp_localize_script( 'ivy-st-settings', 'IVY_ST', $shared );
		}

		if ( $page === self::SLUG_NEW || $page === self::SLUG_SHOW ) {
			// 첨부 업로더 — new/show 양쪽에서 동일 모듈을 공유한다.
			wp_enqueue_script(
				'ivy-st-attach-uploader',
				IVY_ST_PLUGIN_URL . 'assets/js/attach-uploader.js',
				array( 'jquery' ),
				IVY_ST_VERSION,
				true
			);
		}

		if ( $page === self::SLUG_NEW ) {
			wp_enqueue_script(
				'ivy-st-ticket-new',
				IVY_ST_PLUGIN_URL . 'assets/js/ticket-new.js',
				array( 'jquery', 'ivy-st-attach-uploader' ),
				IVY_ST_VERSION,
				true
			);
			wp_localize_script( 'ivy-st-ticket-new', 'IVY_ST', $shared );
		}

		if ( $page === self::SLUG_SHOW ) {
			wp_enqueue_script(
				'ivy-st-ticket-show',
				IVY_ST_PLUGIN_URL . 'assets/js/ticket-show.js',
				array( 'jquery', 'ivy-st-attach-uploader' ),
				IVY_ST_VERSION,
				true
			);
			wp_localize_script( 'ivy-st-ticket-show', 'IVY_ST', $shared );
		}
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

	/**
	 * 설정 폼 제출 — admin-post 핸들러.
	 *
	 * 폼이 단일이지만 표시되는 필드는 활성 탭 한 곳뿐이므로, hidden tab 값을
	 * 기준으로 해당 탭의 필드만 patch에 포함시킨다. 그렇지 않으면 다른 탭의
	 * 값(예: allowed_user_ids)이 빈 배열로 덮어써질 수 있다.
	 */
	public static function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '권한이 없습니다.', 'ivy-support-ticket' ) );
		}
		check_admin_referer( 'ivy_st_save_settings' );

		$tab   = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'connection';
		$patch = array();

		if ( $tab === 'connection' ) {
			if ( isset( $_POST['api_base'] ) ) {
				$patch['api_base'] = wp_unslash( $_POST['api_base'] );
			}
			// API Key는 입력 시에만 갱신, 빈 값이면 Settings::update에서 기존 유지.
			if ( isset( $_POST['api_key'] ) ) {
				$patch['api_key'] = wp_unslash( $_POST['api_key'] );
			}
		} elseif ( $tab === 'info' ) {
			$patch['debug'] = ! empty( $_POST['debug'] );
		}
		// users 탭은 더 이상 폼 submit 항목이 없다 (검색·추가·해지는 AJAX 전용).

		if ( ! empty( $patch ) ) {
			Settings::update( $patch );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG_SETTING,
					'tab'     => $tab,
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

	/** 새 티켓 생성 AJAX 핸들러. */
	public static function ajax_create_ticket(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}

		$user = wp_get_current_user();
		$api  = new ApiClient();

		// 매핑은 ApiClient 호출 전에 한 번 더 보장.
		$mapping = UserMapping::ensure_for_current_user( $api );
		if ( is_wp_error( $mapping ) ) {
			wp_send_json_error( array( 'message' => $mapping->get_error_message() ) );
		}

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$priority    = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'NORMAL';
		$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$budget      = isset( $_POST['budget'] ) ? sanitize_text_field( wp_unslash( $_POST['budget'] ) ) : '';
		$deadline    = isset( $_POST['deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['deadline'] ) ) : '';
		$refs_raw    = isset( $_POST['referenceUrls'] ) ? wp_unslash( $_POST['referenceUrls'] ) : '';

		if ( $title === '' || mb_strlen( $title ) < 2 || mb_strlen( $title ) > 200 ) {
			wp_send_json_error( array( 'message' => __( '제목은 2~200자여야 합니다.', 'ivy-support-ticket' ) ) );
		}
		if ( trim( wp_strip_all_tags( $description ) ) === '' ) {
			wp_send_json_error( array( 'message' => __( '내용을 입력하세요.', 'ivy-support-ticket' ) ) );
		}

		$ref_urls = array();
		if ( is_string( $refs_raw ) && $refs_raw !== '' ) {
			foreach ( preg_split( '/\r?\n/', $refs_raw ) as $line ) {
				$u = trim( (string) $line );
				if ( $u === '' ) {
					continue;
				}
				$valid = filter_var( $u, FILTER_VALIDATE_URL );
				if ( $valid ) {
					$ref_urls[] = $valid;
				}
			}
		}

		$metadata = array();
		if ( $budget !== '' ) {
			$metadata['budget'] = $budget;
		}
		if ( $deadline !== '' ) {
			$metadata['deadline'] = $deadline;
		}
		if ( ! empty( $ref_urls ) ) {
			$metadata['referenceUrls'] = $ref_urls;
		}

		$payload = array(
			'userEmail'   => $user->user_email,
			'title'       => $title,
			'description' => $description,
			'category'    => $category,
			'priority'    => $priority,
		);
		if ( ! empty( $metadata ) ) {
			$payload['metadata'] = $metadata;
		}

		$attachments = self::sanitize_attachments_param();
		if ( ! empty( $attachments ) ) {
			$payload['attachments'] = $attachments;
		}

		$result = $api->create_ticket( $payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$show_url = add_query_arg(
			array(
				'page' => self::SLUG_SHOW,
				'id'   => isset( $result['id'] ) ? $result['id'] : '',
			),
			admin_url( 'admin.php' )
		);
		wp_send_json_success(
			array(
				'ticket'  => $result,
				'showUrl' => $show_url,
			)
		);
	}

	/** 댓글 작성 AJAX 핸들러. */
	public static function ajax_add_comment(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}

		$user = wp_get_current_user();
		$api  = new ApiClient();

		$mapping = UserMapping::ensure_for_current_user( $api );
		if ( is_wp_error( $mapping ) ) {
			wp_send_json_error( array( 'message' => $mapping->get_error_message() ) );
		}

		$ticket_id = isset( $_POST['ticketId'] ) ? sanitize_text_field( wp_unslash( $_POST['ticketId'] ) ) : '';
		$body      = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		$attachments = self::sanitize_attachments_param();

		if ( $ticket_id === '' ) {
			wp_send_json_error( array( 'message' => __( '티켓 ID가 누락되었습니다.', 'ivy-support-ticket' ) ) );
		}
		if ( trim( wp_strip_all_tags( $body ) ) === '' ) {
			wp_send_json_error( array( 'message' => __( '댓글 내용을 입력하세요.', 'ivy-support-ticket' ) ) );
		}

		$payload = array(
			'userEmail' => $user->user_email,
			'body'      => $body,
		);
		if ( ! empty( $attachments ) ) {
			$payload['attachments'] = $attachments;
		}

		$result = $api->add_comment( $ticket_id, $payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * R2 PutObject용 presigned URL 발급 — 브라우저가 파일을 직접 PUT하기 직전에 호출.
	 */
	public static function ajax_presign(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}

		$filename  = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';
		$mime_type = isset( $_POST['mimeType'] ) ? sanitize_text_field( wp_unslash( $_POST['mimeType'] ) ) : '';
		$file_size = isset( $_POST['fileSize'] ) ? absint( $_POST['fileSize'] ) : 0;

		if ( $filename === '' || $mime_type === '' || $file_size <= 0 ) {
			wp_send_json_error( array( 'message' => __( '잘못된 파일 정보입니다.', 'ivy-support-ticket' ) ) );
		}
		if ( $file_size > 10 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( '파일 크기는 10MB 이하여야 합니다.', 'ivy-support-ticket' ) ) );
		}

		$api    = new ApiClient();
		$result = $api->presign_put( $filename, $mime_type, $file_size );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * R2 GetObject용 presigned URL 발급 — 첨부 다운로드 클릭 시 호출.
	 */
	public static function ajax_presign_get(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}

		$r2_key = isset( $_POST['r2Key'] ) ? sanitize_text_field( wp_unslash( $_POST['r2Key'] ) ) : '';
		if ( $r2_key === '' ) {
			wp_send_json_error( array( 'message' => __( 'r2Key가 필요합니다.', 'ivy-support-ticket' ) ) );
		}

		$api    = new ApiClient();
		$result = $api->presign_get( $r2_key );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * 사용자 매핑 — 이메일/이름/아이디로 WP 사용자를 검색해 후보 목록을 반환.
	 * `IVY_ST_OPT_SETTINGS.allowed_user_ids`에 이미 포함된 사용자는 `enrolled=true`로 표시.
	 */
	public static function ajax_user_search(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}

		$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
		if ( $q === '' || mb_strlen( $q ) < 2 ) {
			wp_send_json_error(
				array( 'message' => __( '검색어는 2자 이상 입력해 주세요.', 'ivy-support-ticket' ) )
			);
		}

		$settings    = Settings::get();
		$enrolled_ids = array_map( 'absint', (array) $settings['allowed_user_ids'] );

		$query = new \WP_User_Query(
			array(
				'search'         => '*' . $q . '*',
				'search_columns' => array( 'user_email', 'user_login', 'display_name' ),
				'fields'         => array( 'ID', 'user_email', 'user_login', 'display_name' ),
				'orderby'        => 'display_name',
				'order'          => 'ASC',
				'number'         => 30,
			)
		);
		$results = array();
		foreach ( (array) $query->get_results() as $u ) {
			$wp_user = get_userdata( (int) $u->ID );
			$roles   = $wp_user ? (array) $wp_user->roles : array();
			$results[] = array(
				'id'           => (int) $u->ID,
				'display_name' => (string) $u->display_name,
				'email'        => (string) $u->user_email,
				'login'        => (string) $u->user_login,
				'roles'        => $roles,
				'enrolled'     => in_array( (int) $u->ID, $enrolled_ids, true ),
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/** 사용자 매핑 — 단건 추가. 갱신된 등록 사용자 목록 전체를 응답으로 반환. */
	public static function ajax_user_add(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '유효하지 않은 사용자 ID입니다.', 'ivy-support-ticket' ) ) );
		}
		$wp_user = get_userdata( $user_id );
		if ( ! $wp_user ) {
			wp_send_json_error( array( 'message' => __( '존재하지 않는 사용자입니다.', 'ivy-support-ticket' ) ) );
		}

		$settings = Settings::get();
		$ids      = array_map( 'absint', (array) $settings['allowed_user_ids'] );
		if ( ! in_array( $user_id, $ids, true ) ) {
			$ids[] = $user_id;
			Settings::update( array( 'allowed_user_ids' => $ids ) );
		}

		wp_send_json_success(
			array(
				'enrolled' => self::collect_enrolled_users(),
			)
		);
	}

	/** 사용자 매핑 — 단건 해지. 갱신된 등록 사용자 목록 전체를 응답으로 반환. */
	public static function ajax_user_remove(): void {
		check_ajax_referer( self::NONCE_AJAX );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'ivy-support-ticket' ) ), 403 );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '유효하지 않은 사용자 ID입니다.', 'ivy-support-ticket' ) ) );
		}

		$settings = Settings::get();
		$ids      = array_map( 'absint', (array) $settings['allowed_user_ids'] );
		$ids      = array_values( array_filter( $ids, static fn( $id ) => $id !== $user_id ) );
		Settings::update( array( 'allowed_user_ids' => $ids ) );

		wp_send_json_success(
			array(
				'enrolled' => self::collect_enrolled_users(),
			)
		);
	}

	/** 현재 옵션의 allowed_user_ids에 대응하는 UI 표현 배열을 수집한다. */
	public static function collect_enrolled_users(): array {
		$s   = Settings::get();
		$ids = array_map( 'absint', (array) $s['allowed_user_ids'] );
		$out = array();
		foreach ( $ids as $uid ) {
			$wp_user = get_userdata( $uid );
			if ( ! $wp_user ) {
				continue;
			}
			$out[] = self::format_user_for_ui( $wp_user );
		}
		return $out;
	}

	/**
	 * UI에 사용할 사용자 객체 표현 — 검색 결과·등록 목록 양쪽에서 공유.
	 *
	 * @param \WP_User $u
	 * @return array
	 */
	public static function format_user_for_ui( \WP_User $u ): array {
		$pm_user_id = (string) get_user_meta( (int) $u->ID, IVY_ST_USERMETA_PM_USER_ID, true );
		return array(
			'id'           => (int) $u->ID,
			'display_name' => (string) $u->display_name,
			'email'        => (string) $u->user_email,
			'login'        => (string) $u->user_login,
			'roles'        => (array) $u->roles,
			'pm_user_id'   => $pm_user_id,
		);
	}

	/**
	 * 폼에서 보내온 attachments JSON 문자열을 검증된 배열로 변환한다.
	 * 각 첨부는 `{r2Key, fileName, fileSize, mimeType}` 4종 필드를 모두 가져야 하며,
	 * 최대 5개로 제한한다.
	 *
	 * @return array
	 */
	private static function sanitize_attachments_param(): array {
		$raw = isset( $_POST['attachments'] ) ? wp_unslash( $_POST['attachments'] ) : '';
		if ( ! is_string( $raw ) || $raw === '' ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$r2_key    = isset( $item['r2Key'] ) ? (string) $item['r2Key'] : '';
			$file_name = isset( $item['fileName'] ) ? (string) $item['fileName'] : '';
			$file_size = isset( $item['fileSize'] ) ? (int) $item['fileSize'] : 0;
			$mime_type = isset( $item['mimeType'] ) ? (string) $item['mimeType'] : '';
			if ( $r2_key === '' || $file_name === '' || $file_size <= 0 || $mime_type === '' ) {
				continue;
			}
			$out[] = array(
				'r2Key'    => $r2_key,
				'fileName' => sanitize_text_field( $file_name ),
				'fileSize' => $file_size,
				'mimeType' => sanitize_text_field( $mime_type ),
			);
			if ( count( $out ) >= 5 ) {
				break;
			}
		}
		return $out;
	}
}
