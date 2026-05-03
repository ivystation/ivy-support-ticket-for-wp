<?php
/**
 * Labels: pm 시스템의 enum 값(영문)을 한국어 라벨로 변환한다.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class Labels {

	public static function category( string $value ): string {
		$map = array(
			'WORDPRESS'   => __( '워드프레스', 'ivy-support-ticket' ),
			'AI'          => __( 'AI', 'ivy-support-ticket' ),
			'DESIGN'      => __( '디자인', 'ivy-support-ticket' ),
			'MAINTENANCE' => __( '유지보수', 'ivy-support-ticket' ),
			'CONSULTING'  => __( '컨설팅', 'ivy-support-ticket' ),
			'OTHER'       => __( '기타', 'ivy-support-ticket' ),
		);
		return $map[ $value ] ?? $value;
	}

	public static function priority( string $value ): string {
		$map = array(
			'LOW'    => __( '낮음', 'ivy-support-ticket' ),
			'NORMAL' => __( '보통', 'ivy-support-ticket' ),
			'HIGH'   => __( '높음', 'ivy-support-ticket' ),
			'URGENT' => __( '긴급', 'ivy-support-ticket' ),
		);
		return $map[ $value ] ?? $value;
	}

	public static function status( string $value ): string {
		$map = array(
			'DRAFT'       => __( '임시저장', 'ivy-support-ticket' ),
			'SUBMITTED'   => __( '접수', 'ivy-support-ticket' ),
			'IN_REVIEW'   => __( '검토중', 'ivy-support-ticket' ),
			'QUOTED'      => __( '견적완료', 'ivy-support-ticket' ),
			'ACCEPTED'    => __( '승인', 'ivy-support-ticket' ),
			'IN_PROGRESS' => __( '진행중', 'ivy-support-ticket' ),
			'COMPLETED'   => __( '완료', 'ivy-support-ticket' ),
			'CLOSED'      => __( '종료', 'ivy-support-ticket' ),
			'REJECTED'    => __( '반려', 'ivy-support-ticket' ),
			'CANCELLED'   => __( '취소', 'ivy-support-ticket' ),
			'ON_HOLD'     => __( '보류', 'ivy-support-ticket' ),
		);
		return $map[ $value ] ?? $value;
	}

	/** 모든 카테고리의 [value => label] 맵 — select 옵션에 사용. */
	public static function all_categories(): array {
		return array(
			'WORDPRESS'   => self::category( 'WORDPRESS' ),
			'AI'          => self::category( 'AI' ),
			'DESIGN'      => self::category( 'DESIGN' ),
			'MAINTENANCE' => self::category( 'MAINTENANCE' ),
			'CONSULTING'  => self::category( 'CONSULTING' ),
			'OTHER'       => self::category( 'OTHER' ),
		);
	}

	public static function all_priorities(): array {
		return array(
			'LOW'    => self::priority( 'LOW' ),
			'NORMAL' => self::priority( 'NORMAL' ),
			'HIGH'   => self::priority( 'HIGH' ),
			'URGENT' => self::priority( 'URGENT' ),
		);
	}

	/** 상태 탭 매핑: 탭 키 → API에 보낼 status 콤마 구분 문자열. */
	public static function status_tabs(): array {
		return array(
			'all'      => array( 'label' => __( '전체', 'ivy-support-ticket' ),     'statuses' => '' ),
			'open'     => array( 'label' => __( '접수', 'ivy-support-ticket' ),     'statuses' => 'SUBMITTED,IN_REVIEW' ),
			'progress' => array( 'label' => __( '진행중', 'ivy-support-ticket' ),   'statuses' => 'QUOTED,ACCEPTED,IN_PROGRESS' ),
			'done'     => array( 'label' => __( '완료', 'ivy-support-ticket' ),     'statuses' => 'COMPLETED,CLOSED' ),
		);
	}
}
