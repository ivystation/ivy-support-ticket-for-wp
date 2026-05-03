<?php
/**
 * 티켓 목록 페이지: pm /api/external/wp/tickets에서 받아온 데이터를 WP_List_Table로 표시.
 */

defined( 'ABSPATH' ) || exit;

$user      = wp_get_current_user();
$api       = new \IvyST\ApiClient();
$mapping   = \IvyST\UserMapping::ensure_for_current_user( $api );
$tabs      = \IvyST\Labels::status_tabs();
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'all';
if ( ! isset( $tabs[ $active_tab ] ) ) {
	$active_tab = 'all';
}
$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

?>
<div class="wrap ivy-st-page ivy-st-list-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( '티켓 목록', 'ivy-support-ticket' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \IvyST\AdminPages::SLUG_NEW ) ); ?>" class="page-title-action">
		<?php esc_html_e( '새 티켓 작성', 'ivy-support-ticket' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php if ( is_wp_error( $mapping ) ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( '사용자 매핑 실패', 'ivy-support-ticket' ); ?>:</strong>
				<?php echo esc_html( $mapping->get_error_message() ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( '관리자에게 문의하거나, 설정 페이지에서 API 연결과 사용자 매핑을 확인해 주세요.', 'ivy-support-ticket' ); ?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<ul class="subsubsub ivy-st-tabs">
		<?php $i = 0; foreach ( $tabs as $key => $tab ) : $i++; ?>
			<li>
				<a href="<?php echo esc_url( add_query_arg( array( 'tab' => $key, 'paged' => 1 ) ) ); ?>"
				   class="<?php echo $key === $active_tab ? 'current' : ''; ?>">
					<?php echo esc_html( $tab['label'] ); ?>
				</a><?php echo $i < count( $tabs ) ? ' |' : ''; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<br class="clear" />

	<?php
	$response = $api->list_tickets(
		array(
			'userEmail' => $user->user_email,
			'page'      => $page,
			'status'    => (string) $tabs[ $active_tab ]['statuses'],
		)
	);

	if ( is_wp_error( $response ) ) :
	?>
		<div class="notice notice-error">
			<p><?php echo esc_html( $response->get_error_message() ); ?></p>
		</div>
	<?php
	else :
		$tickets    = isset( $response['tickets'] ) && is_array( $response['tickets'] ) ? $response['tickets'] : array();
		$total      = isset( $response['total'] ) ? (int) $response['total'] : 0;

		$table = new \IvyST\Ticket_List_Table();
		$table->set_data( $tickets, $total );
		$table->prepare_items();
		?>
		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( \IvyST\AdminPages::SLUG_LIST ); ?>" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>" />
			<?php $table->display(); ?>
		</form>
	<?php endif; ?>
</div>
