<?php
/**
 * ApiClient: pm.ivynet.co.kr `/api/external/wp/*` 호출 래퍼.
 *
 * 모든 메서드는 성공 시 디코딩된 배열을, 실패 시 WP_Error를 반환한다.
 * pm 측 응답 스키마: `{ ok: true, data: ... }` 또는 `{ ok: false, error, details? }`.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class ApiClient {

	private string $base;
	private string $key;
	private bool $debug;

	public function __construct() {
		$s = Settings::get();
		$this->base  = rtrim( (string) $s['api_base'], '/' );
		$this->key   = (string) $s['api_key'];
		$this->debug = (bool) $s['debug'];
	}

	/**
	 * 키 검증 + 조직 정보. 설정 페이지의 "연결 테스트" 버튼이 호출한다.
	 *
	 * @return array|\WP_Error
	 */
	public function health() {
		return $this->request( 'GET', '/api/external/wp/health' );
	}

	/**
	 * WP user 이메일을 pm User로 upsert. 응답: `{ userId, email, name, created }`.
	 *
	 * @return array|\WP_Error
	 */
	public function ensure_user( string $email, string $name ) {
		return $this->request(
			'POST',
			'/api/external/wp/users/ensure',
			array(
				'email' => $email,
				'name'  => $name,
			)
		);
	}

	/**
	 * 사용자의 티켓 목록.
	 *
	 * @param array{userEmail:string,page?:int,status?:string} $params
	 * @return array|\WP_Error
	 */
	public function list_tickets( array $params ) {
		$qs = http_build_query(
			array_filter(
				array(
					'userEmail' => $params['userEmail'] ?? null,
					'page'      => isset( $params['page'] ) ? (int) $params['page'] : null,
					'status'    => $params['status'] ?? null,
				),
				static fn( $v ) => $v !== null && $v !== ''
			)
		);
		return $this->request( 'GET', '/api/external/wp/tickets?' . $qs );
	}

	/**
	 * 티켓 상세.
	 *
	 * @return array|\WP_Error
	 */
	public function get_ticket( string $ticket_id, string $user_email ) {
		$qs = http_build_query( array( 'userEmail' => $user_email ) );
		return $this->request( 'GET', '/api/external/wp/tickets/' . rawurlencode( $ticket_id ) . '?' . $qs );
	}

	/**
	 * 새 티켓 생성.
	 *
	 * @return array|\WP_Error
	 */
	public function create_ticket( array $payload ) {
		return $this->request( 'POST', '/api/external/wp/tickets', $payload );
	}

	/**
	 * 댓글 작성.
	 *
	 * @return array|\WP_Error
	 */
	public function add_comment( string $ticket_id, array $payload ) {
		return $this->request(
			'POST',
			'/api/external/wp/tickets/' . rawurlencode( $ticket_id ) . '/comments',
			$payload
		);
	}

	/**
	 * R2 PutObject용 presigned URL.
	 *
	 * @return array|\WP_Error
	 */
	public function presign_put( string $filename, string $mime_type, int $file_size ) {
		return $this->request(
			'POST',
			'/api/external/wp/uploads/presign',
			array(
				'filename' => $filename,
				'mimeType' => $mime_type,
				'fileSize' => $file_size,
			)
		);
	}

	/**
	 * R2 GetObject용 presigned URL.
	 *
	 * @return array|\WP_Error
	 */
	public function presign_get( string $r2_key ) {
		return $this->request(
			'POST',
			'/api/external/wp/uploads/presign-get',
			array( 'r2Key' => $r2_key )
		);
	}

	/**
	 * 공통 HTTP 요청. Authorization Bearer 헤더를 자동 부착한다.
	 *
	 * @return array|\WP_Error  성공 시 응답 본문의 `data` 부분만 반환.
	 */
	private function request( string $method, string $path, ?array $body = null ) {
		if ( $this->base === '' ) {
			return new \WP_Error( 'ivy_st_missing_base', __( 'API Base URL이 설정되지 않았습니다.', 'ivy-support-ticket' ) );
		}
		if ( $this->key === '' ) {
			return new \WP_Error( 'ivy_st_missing_key', __( 'API Key가 설정되지 않았습니다.', 'ivy-support-ticket' ) );
		}

		$url = $this->base . $path;

		$args = array(
			'method'      => $method,
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $this->key,
				'Accept'        => 'application/json',
				'User-Agent'    => 'IvySupportTicket-WP/' . IVY_ST_VERSION . '; ' . home_url(),
			),
		);

		if ( $body !== null && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'http_error', array( 'method' => $method, 'path' => $path, 'message' => $response->get_error_message() ) );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$json   = json_decode( $raw, true );
		$this->log( 'response', array( 'method' => $method, 'path' => $path, 'status' => $status ) );

		if ( ! is_array( $json ) ) {
			return new \WP_Error(
				'ivy_st_bad_json',
				__( '서버 응답을 해석할 수 없습니다.', 'ivy-support-ticket' ),
				array( 'status' => $status, 'raw' => $raw )
			);
		}

		if ( ! ( isset( $json['ok'] ) && $json['ok'] === true ) ) {
			$msg = isset( $json['error'] ) ? (string) $json['error'] : __( '알 수 없는 오류가 발생했습니다.', 'ivy-support-ticket' );
			return new \WP_Error(
				'ivy_st_api_error',
				$msg,
				array( 'status' => $status, 'details' => $json['details'] ?? null )
			);
		}

		return $json['data'] ?? array();
	}

	/** 디버그 모드일 때만 wp-content/uploads/ivy-support-ticket-debug.log에 기록 (API Key 마스킹). */
	private function log( string $event, array $context ): void {
		if ( ! $this->debug ) {
			return;
		}
		$upload = wp_upload_dir();
		if ( empty( $upload['basedir'] ) ) {
			return;
		}
		$file = trailingslashit( $upload['basedir'] ) . 'ivy-support-ticket-debug.log';
		$line = sprintf(
			'[%s] %s %s' . PHP_EOL,
			gmdate( 'c' ),
			$event,
			wp_json_encode( $context )
		);
		// 베스트 에포트 — 실패 시 무시.
		@file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
	}
}
