<?php
/**
 * 새 티켓 작성: TinyMCE 기반 본문 + 카테고리/우선순위 + 메타.
 * 첨부파일 업로드는 Phase 4에서 추가된다.
 */

defined( 'ABSPATH' ) || exit;

$user    = wp_get_current_user();
$api     = new \IvyST\ApiClient();
$mapping = \IvyST\UserMapping::ensure_for_current_user( $api );
?>
<div class="wrap ivy-st-page ivy-st-new-page">
	<h1><?php esc_html_e( '새 티켓 작성', 'ivy-support-ticket' ); ?></h1>

	<?php if ( is_wp_error( $mapping ) ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( '사용자 매핑 실패', 'ivy-support-ticket' ); ?>:</strong>
				<?php echo esc_html( $mapping->get_error_message() ); ?>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div id="ivy-st-new-result" class="ivy-st-result" aria-live="polite"></div>

	<form id="ivy-st-new-form" class="ivy-st-form">
		<?php wp_nonce_field( \IvyST\AdminPages::NONCE_AJAX, '_wpnonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="ivy-st-title"><?php esc_html_e( '제목', 'ivy-support-ticket' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="text" id="ivy-st-title" name="title" class="regular-text" maxlength="200" required />
					<p class="description"><?php esc_html_e( '2~200자', 'ivy-support-ticket' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ivy-st-category"><?php esc_html_e( '카테고리', 'ivy-support-ticket' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<select id="ivy-st-category" name="category" required>
						<?php foreach ( \IvyST\Labels::all_categories() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ivy-st-priority"><?php esc_html_e( '우선순위', 'ivy-support-ticket' ); ?></label>
				</th>
				<td>
					<select id="ivy-st-priority" name="priority">
						<?php foreach ( \IvyST\Labels::all_priorities() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, 'NORMAL' ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ivy-st-description"><?php esc_html_e( '내용', 'ivy-support-ticket' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<?php
					wp_editor(
						'',
						'ivy-st-description',
						array(
							'textarea_name' => 'description',
							'textarea_rows' => 14,
							// Phase 4 이전까지는 미디어 라이브러리 업로드 비활성 — 첨부 흐름이 다름.
							'media_buttons' => false,
							'tinymce'       => array(
								'toolbar1' => 'bold,italic,underline,bullist,numlist,blockquote,link,unlink,undo,redo',
								'toolbar2' => '',
							),
							'quicktags'     => true,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'HTML 형식으로 저장됩니다. 이미지·첨부파일 업로드는 곧 지원됩니다.', 'ivy-support-ticket' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="ivy-st-section-h"><?php esc_html_e( '첨부파일', 'ivy-support-ticket' ); ?></h2>
		<div class="ivy-st-attach-area">
			<input type="file" id="ivy-st-attach-input" multiple
			       accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,text/plain,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip,application/x-zip-compressed" />
			<p class="description">
				<?php esc_html_e( '최대 5개, 각 10MB 이하. 이미지·PDF·문서·압축파일.', 'ivy-support-ticket' ); ?>
			</p>
			<ul id="ivy-st-attach-list" class="ivy-st-attach-list" aria-live="polite"></ul>
			<input type="hidden" name="attachments" id="ivy-st-attach-data" value="[]" />
		</div>

		<h2 class="ivy-st-section-h"><?php esc_html_e( '추가 정보 (선택)', 'ivy-support-ticket' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ivy-st-budget"><?php esc_html_e( '예산', 'ivy-support-ticket' ); ?></label></th>
				<td><input type="text" id="ivy-st-budget" name="budget" class="regular-text" placeholder="<?php esc_attr_e( '예: 5,000,000원', 'ivy-support-ticket' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ivy-st-deadline"><?php esc_html_e( '완료 희망일', 'ivy-support-ticket' ); ?></label></th>
				<td><input type="date" id="ivy-st-deadline" name="deadline" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ivy-st-references"><?php esc_html_e( '참고 URL', 'ivy-support-ticket' ); ?></label></th>
				<td>
					<textarea id="ivy-st-references" name="referenceUrls" rows="3" class="large-text" placeholder="https://… (한 줄에 하나)"></textarea>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" id="ivy-st-submit" class="button button-primary">
				<?php esc_html_e( '티켓 제출', 'ivy-support-ticket' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \IvyST\AdminPages::SLUG_LIST ) ); ?>" class="button">
				<?php esc_html_e( '취소', 'ivy-support-ticket' ); ?>
			</a>
		</p>
	</form>
</div>
