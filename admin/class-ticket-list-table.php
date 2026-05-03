<?php
/**
 * Ticket_List_Table: WP_List_Table을 상속해 pm.ivynet.co.kr API에서 받아온 티켓을 표로 렌더링한다.
 *
 * 데이터 소스가 외부 API이므로 페이지네이션·정렬은 API의 응답을 그대로 사용한다.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Ticket_List_Table extends \WP_List_Table {

	/** API 응답의 tickets 배열. */
	private array $tickets = array();

	/** API 응답의 total. */
	private int $total = 0;

	/** 페이지당 항목 수 — pm /api/external/wp/tickets는 20 고정. */
	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ticket',
				'plural'   => 'tickets',
				'ajax'     => false,
			)
		);
	}

	public function set_data( array $tickets, int $total ): void {
		$this->tickets = $tickets;
		$this->total   = $total;
	}

	public function get_columns() {
		return array(
			'ticketNumber' => __( '번호', 'ivy-support-ticket' ),
			'title'        => __( '제목', 'ivy-support-ticket' ),
			'category'     => __( '카테고리', 'ivy-support-ticket' ),
			'priority'     => __( '우선순위', 'ivy-support-ticket' ),
			'status'       => __( '상태', 'ivy-support-ticket' ),
			'comments'     => __( '댓글', 'ivy-support-ticket' ),
			'createdAt'    => __( '작성일', 'ivy-support-ticket' ),
		);
	}

	public function get_sortable_columns() {
		// 외부 API는 createdAt desc 고정이라 정렬은 비활성화.
		return array();
	}

	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = $this->tickets;

		$this->set_pagination_args(
			array(
				'total_items' => $this->total,
				'per_page'    => self::PER_PAGE,
				'total_pages' => max( 1, (int) ceil( $this->total / self::PER_PAGE ) ),
			)
		);
	}

	/**
	 * 알 수 없는 컬럼 — fallback.
	 *
	 * @param array  $item
	 * @param string $column_name
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	public function column_ticketNumber( $item ) {
		$show_url = add_query_arg(
			array(
				'page' => AdminPages::SLUG_SHOW,
				'id'   => isset( $item['id'] ) ? $item['id'] : '',
			),
			admin_url( 'admin.php' )
		);
		return sprintf(
			'<a href="%s"><code>%s</code></a>',
			esc_url( $show_url ),
			esc_html( (string) ( $item['ticketNumber'] ?? '' ) )
		);
	}

	public function column_title( $item ) {
		$show_url = add_query_arg(
			array(
				'page' => AdminPages::SLUG_SHOW,
				'id'   => isset( $item['id'] ) ? $item['id'] : '',
			),
			admin_url( 'admin.php' )
		);
		return sprintf(
			'<strong><a href="%s">%s</a></strong>',
			esc_url( $show_url ),
			esc_html( (string) ( $item['title'] ?? '' ) )
		);
	}

	public function column_category( $item ) {
		return esc_html( Labels::category( (string) ( $item['category'] ?? '' ) ) );
	}

	public function column_priority( $item ) {
		$value = (string) ( $item['priority'] ?? '' );
		return sprintf(
			'<span class="ivy-st-priority ivy-st-priority-%s">%s</span>',
			esc_attr( strtolower( $value ) ),
			esc_html( Labels::priority( $value ) )
		);
	}

	public function column_status( $item ) {
		$value = (string) ( $item['status'] ?? '' );
		return sprintf(
			'<span class="ivy-st-status ivy-st-status-%s">%s</span>',
			esc_attr( strtolower( $value ) ),
			esc_html( Labels::status( $value ) )
		);
	}

	public function column_comments( $item ) {
		$count = isset( $item['_count']['comments'] ) ? (int) $item['_count']['comments'] : 0;
		if ( $count <= 0 ) {
			return '<span class="ivy-st-zero">0</span>';
		}
		return sprintf( '<strong>%d</strong>', $count );
	}

	public function column_createdAt( $item ) {
		$raw = (string) ( $item['createdAt'] ?? '' );
		if ( $raw === '' ) {
			return '';
		}
		$ts = strtotime( $raw );
		if ( ! $ts ) {
			return esc_html( $raw );
		}
		return esc_html( wp_date( 'Y-m-d H:i', $ts ) );
	}

	/** 비어있을 때 표시 메시지. */
	public function no_items() {
		esc_html_e( '표시할 티켓이 없습니다.', 'ivy-support-ticket' );
	}
}
