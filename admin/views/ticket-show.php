<?php
/**
 * 티켓 상세: 본문 + 메타 + 댓글 목록 + 댓글 작성 폼.
 * 첨부파일 다운로드는 Phase 4에서 활성화된다.
 */

defined( 'ABSPATH' ) || exit;

$user      = wp_get_current_user();
$api       = new \IvyST\ApiClient();
$mapping   = \IvyST\UserMapping::ensure_for_current_user( $api );
$ticket_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
?>
<div class="wrap ivy-st-page ivy-st-show-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( '티켓 상세', 'ivy-support-ticket' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \IvyST\AdminPages::SLUG_LIST ) ); ?>" class="page-title-action">
		<?php esc_html_e( '← 목록으로', 'ivy-support-ticket' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php if ( is_wp_error( $mapping ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $mapping->get_error_message() ); ?></p></div>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( $ticket_id === '' ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( '티켓 ID가 지정되지 않았습니다.', 'ivy-support-ticket' ); ?></p></div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	$ticket = $api->get_ticket( $ticket_id, $user->user_email );
	if ( is_wp_error( $ticket ) ) :
	?>
		<div class="notice notice-error"><p><?php echo esc_html( $ticket->get_error_message() ); ?></p></div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	$created  = isset( $ticket['createdAt'] ) ? strtotime( (string) $ticket['createdAt'] ) : false;
	$metadata = isset( $ticket['metadata'] ) && is_array( $ticket['metadata'] ) ? $ticket['metadata'] : array();
	$comments = isset( $ticket['comments'] ) && is_array( $ticket['comments'] ) ? $ticket['comments'] : array();
	?>

	<div class="ivy-st-detail-header">
		<div class="ivy-st-detail-title">
			<code class="ivy-st-ticket-number"><?php echo esc_html( (string) ( $ticket['ticketNumber'] ?? '' ) ); ?></code>
			<h2><?php echo esc_html( (string) ( $ticket['title'] ?? '' ) ); ?></h2>
		</div>
		<span class="ivy-st-status ivy-st-status-<?php echo esc_attr( strtolower( (string) ( $ticket['status'] ?? '' ) ) ); ?>">
			<?php echo esc_html( \IvyST\Labels::status( (string) ( $ticket['status'] ?? '' ) ) ); ?>
		</span>
	</div>

	<table class="ivy-st-meta">
		<tr>
			<th><?php esc_html_e( '카테고리', 'ivy-support-ticket' ); ?></th>
			<td><?php echo esc_html( \IvyST\Labels::category( (string) ( $ticket['category'] ?? '' ) ) ); ?></td>
			<th><?php esc_html_e( '우선순위', 'ivy-support-ticket' ); ?></th>
			<td>
				<span class="ivy-st-priority ivy-st-priority-<?php echo esc_attr( strtolower( (string) ( $ticket['priority'] ?? '' ) ) ); ?>">
					<?php echo esc_html( \IvyST\Labels::priority( (string) ( $ticket['priority'] ?? '' ) ) ); ?>
				</span>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( '작성일', 'ivy-support-ticket' ); ?></th>
			<td><?php echo $created ? esc_html( wp_date( 'Y-m-d H:i', $created ) ) : '—'; ?></td>
			<th><?php esc_html_e( '예산 / 마감', 'ivy-support-ticket' ); ?></th>
			<td>
				<?php
				$budget   = isset( $metadata['budget'] ) ? (string) $metadata['budget'] : '';
				$deadline = isset( $metadata['deadline'] ) ? (string) $metadata['deadline'] : '';
				echo esc_html( $budget !== '' ? $budget : '—' );
				echo ' / ';
				echo esc_html( $deadline !== '' ? $deadline : '—' );
				?>
			</td>
		</tr>
		<?php if ( ! empty( $metadata['referenceUrls'] ) && is_array( $metadata['referenceUrls'] ) ) : ?>
			<tr>
				<th><?php esc_html_e( '참고 URL', 'ivy-support-ticket' ); ?></th>
				<td colspan="3">
					<ul class="ivy-st-refs">
						<?php foreach ( $metadata['referenceUrls'] as $u ) : ?>
							<li><a href="<?php echo esc_url( (string) $u ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $u ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		<?php endif; ?>
	</table>

	<h2 class="ivy-st-section-h"><?php esc_html_e( '문의 내용', 'ivy-support-ticket' ); ?></h2>
	<div class="ivy-st-body">
		<?php
		$desc = (string) ( $ticket['description'] ?? '' );
		// pm 측은 HTML 또는 plain text/JSON 문자열 모두 가능. HTML이면 sanitize 후 출력, 아니면 nl2br.
		if ( strip_tags( $desc ) !== $desc ) {
			echo wp_kses_post( $desc );
		} else {
			echo nl2br( esc_html( $desc ) );
		}
		?>
	</div>

	<?php if ( ! empty( $ticket['attachments'] ) && is_array( $ticket['attachments'] ) ) : ?>
		<h2 class="ivy-st-section-h"><?php esc_html_e( '첨부파일', 'ivy-support-ticket' ); ?></h2>
		<ul class="ivy-st-attachments">
			<?php foreach ( $ticket['attachments'] as $a ) : ?>
				<li>
					<span class="dashicons dashicons-paperclip"></span>
					<span class="ivy-st-att-name"><?php echo esc_html( (string) ( $a['fileName'] ?? '' ) ); ?></span>
					<span class="ivy-st-att-size">(<?php echo esc_html( size_format( (int) ( $a['fileSize'] ?? 0 ) ) ); ?>)</span>
					<span class="ivy-st-att-note">— <?php esc_html_e( '다운로드는 다음 단계에서 활성화됩니다.', 'ivy-support-ticket' ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h2 class="ivy-st-section-h"><?php esc_html_e( '댓글', 'ivy-support-ticket' ); ?></h2>

	<ul id="ivy-st-comments" class="ivy-st-comments">
		<?php if ( empty( $comments ) ) : ?>
			<li class="ivy-st-comment ivy-st-comment-empty"><?php esc_html_e( '아직 댓글이 없습니다.', 'ivy-support-ticket' ); ?></li>
		<?php else : ?>
			<?php foreach ( $comments as $c ) :
				$is_staff = isset( $c['author']['userType'] ) && $c['author']['userType'] === 'STAFF';
				$ts       = isset( $c['createdAt'] ) ? strtotime( (string) $c['createdAt'] ) : false;
				$body     = (string) ( $c['body'] ?? '' );
				?>
				<li class="ivy-st-comment <?php echo $is_staff ? 'ivy-st-comment-staff' : 'ivy-st-comment-customer'; ?>">
					<div class="ivy-st-comment-meta">
						<strong><?php echo esc_html( (string) ( $c['author']['name'] ?? '' ) ); ?></strong>
						<?php if ( $is_staff ) : ?>
							<span class="ivy-st-staff-badge"><?php esc_html_e( 'STAFF', 'ivy-support-ticket' ); ?></span>
						<?php endif; ?>
						<span class="ivy-st-comment-time"><?php echo $ts ? esc_html( wp_date( 'Y-m-d H:i', $ts ) ) : ''; ?></span>
					</div>
					<div class="ivy-st-comment-body">
						<?php
						if ( strip_tags( $body ) !== $body ) {
							echo wp_kses_post( $body );
						} else {
							echo nl2br( esc_html( $body ) );
						}
						?>
					</div>
				</li>
			<?php endforeach; ?>
		<?php endif; ?>
	</ul>

	<form id="ivy-st-comment-form" class="ivy-st-form ivy-st-comment-form" data-ticket-id="<?php echo esc_attr( $ticket_id ); ?>">
		<?php wp_nonce_field( \IvyST\AdminPages::NONCE_AJAX, '_wpnonce' ); ?>
		<label for="ivy-st-comment-body" class="screen-reader-text"><?php esc_html_e( '댓글 작성', 'ivy-support-ticket' ); ?></label>
		<textarea id="ivy-st-comment-body" name="body" rows="4" required maxlength="20000" placeholder="<?php esc_attr_e( '댓글을 입력하세요.', 'ivy-support-ticket' ); ?>"></textarea>
		<div class="ivy-st-comment-actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( '댓글 작성', 'ivy-support-ticket' ); ?></button>
			<span id="ivy-st-comment-result" class="ivy-st-result-inline" aria-live="polite"></span>
		</div>
	</form>
</div>
