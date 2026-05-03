<?php
/**
 * Settings: wp_options에 저장되는 플러그인 설정의 단일 게이트웨이.
 *
 * v0.1.4부터 자동 등록(auto_enroll_admin_editor)·기본값 시드 기능을 제거한다.
 * 사용자는 설정 → 사용자 매핑 탭에서 명시적으로 검색·추가해야 한다.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class Settings {

	/** 기본값 — 옵션이 비어 있을 때 폼에 표시될 값. */
	public static function defaults(): array {
		return array(
			'api_base'         => 'https://pm.ivynet.co.kr',
			'api_key'          => '',
			'allowed_user_ids' => array(),
			'debug'            => false,
		);
	}

	/** 현재 설정 + 누락 키는 기본값으로 보충하여 반환. */
	public static function get(): array {
		$saved = get_option( IVY_ST_OPT_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/** 설정 저장 — 알려진 키만 통과시키고 타입 정규화한다. */
	public static function update( array $patch ): array {
		$current  = self::get();
		$next     = $current;

		if ( array_key_exists( 'api_base', $patch ) ) {
			$next['api_base'] = esc_url_raw( trim( (string) $patch['api_base'] ) );
		}
		if ( array_key_exists( 'api_key', $patch ) ) {
			// 빈 문자열 입력은 "변경 없음"으로 해석 — 마스킹된 값을 그대로 보낸 경우 방지.
			$incoming = trim( (string) $patch['api_key'] );
			if ( $incoming !== '' ) {
				$next['api_key'] = $incoming;
			}
		}
		if ( array_key_exists( 'allowed_user_ids', $patch ) ) {
			$ids = (array) $patch['allowed_user_ids'];
			$ids = array_map( 'absint', $ids );
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			$next['allowed_user_ids'] = $ids;
		}
		if ( array_key_exists( 'debug', $patch ) ) {
			$next['debug'] = (bool) $patch['debug'];
		}

		// autoload=no — API Key가 들어 있으므로 모든 페이지 로드에 끌고 다니지 않는다.
		update_option( IVY_ST_OPT_SETTINGS, $next, false );
		return $next;
	}

	/** 특정 WP user가 티켓 페이지에 접근할 수 있는지. */
	public static function user_is_allowed( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$s = self::get();
		return in_array( $user_id, array_map( 'absint', $s['allowed_user_ids'] ), true );
	}

	/** API Key가 등록되어 있는지 (마스킹 표시 여부 판단에 사용). */
	public static function has_api_key(): bool {
		$s = self::get();
		return is_string( $s['api_key'] ) && $s['api_key'] !== '';
	}

	/** API Key를 끝 4자리만 노출하는 마스킹 형태로 반환. */
	public static function masked_api_key(): string {
		$s = self::get();
		$k = (string) $s['api_key'];
		if ( $k === '' ) {
			return '';
		}
		$len = strlen( $k );
		if ( $len <= 12 ) {
			return str_repeat( '*', $len );
		}
		return substr( $k, 0, 9 ) . str_repeat( '*', max( 4, $len - 13 ) ) . substr( $k, -4 );
	}
}
