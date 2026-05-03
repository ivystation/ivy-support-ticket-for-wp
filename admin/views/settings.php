<?php
/**
 * Settings 페이지 뷰. AdminPages::render_settings()에서 require된다.
 *
 * 탭 3개: API 연결 / 사용자 매핑 / 정보. 단일 폼으로 모든 탭을 한 번에 저장한다.
 *
 * @var \IvyST\Settings $_unused  (사용하지 않음 — 클래스 정적 호출만)
 */

defined( 'ABSPATH' ) || exit;

$settings   = \IvyST\Settings::get();
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connection';
$has_key    = \IvyST\Settings::has_api_key();
$masked     = \IvyST\Settings::masked_api_key();

$tabs = array(
	'connection' => __( 'API 연결', 'ivy-support-ticket' ),
	'users'      => __( '사용자 매핑', 'ivy-support-ticket' ),
	'info'       => __( '정보', 'ivy-support-ticket' ),
);

$users = get_users(
	array(
		'fields'  => array( 'ID', 'user_email', 'display_name' ),
		'orderby' => 'display_name',
		'order'   => 'ASC',
		'number'  => -1,
	)
);

if ( ! function_exists( 'wp_get_user_roles_for_select' ) ) {
	/**
	 * 한 user의 역할 라벨을 콤마 구분으로 합쳐 반환.
	 */
	function wp_get_user_roles_for_select( int $user_id ): string {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return '';
		}
		return implode( ', ', (array) $u->roles );
	}
}
?>
<div class="wrap ivy-st-settings">
	<h1><?php esc_html_e( 'Support Ticket 설정', 'ivy-support-ticket' ); ?></h1>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( '설정이 저장되었습니다.', 'ivy-support-ticket' ); ?></p>
		</div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ivy-st-form">
		<input type="hidden" name="action" value="ivy_st_save_settings" />
		<?php wp_nonce_field( 'ivy_st_save_settings' ); ?>
		<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>" />

		<?php if ( $active_tab === 'connection' ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ivy-st-api-base"><?php esc_html_e( 'API Base URL', 'ivy-support-ticket' ); ?></label></th>
					<td>
						<input type="url" id="ivy-st-api-base" name="api_base"
						       value="<?php echo esc_attr( (string) $settings['api_base'] ); ?>"
						       class="regular-text" required />
						<p class="description"><?php esc_html_e( '기본값: https://pm.ivynet.co.kr', 'ivy-support-ticket' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ivy-st-api-key"><?php esc_html_e( 'API Key', 'ivy-support-ticket' ); ?></label></th>
					<td>
						<input type="text" id="ivy-st-api-key" name="api_key"
						       value=""
						       placeholder="<?php echo $has_key ? esc_attr( $masked ) : 'ivk_live_…'; ?>"
						       class="regular-text" autocomplete="off" />
						<p class="description">
							<?php
							echo $has_key
								? esc_html__( '저장된 키가 있습니다. 변경하려면 새 키를 입력하세요. 비워 두면 기존 키 유지.', 'ivy-support-ticket' )
								: esc_html__( 'pm.ivynet.co.kr 어드민에서 발급된 ivk_live_… 형식의 키를 붙여 넣으세요.', 'ivy-support-ticket' );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '연결 테스트', 'ivy-support-ticket' ); ?></th>
					<td>
						<button type="button" id="ivy-st-test-connection" class="button">
							<?php esc_html_e( '연결 테스트', 'ivy-support-ticket' ); ?>
						</button>
						<span id="ivy-st-test-result" class="ivy-st-test-result"></span>
						<p class="description">
							<?php esc_html_e( '저장된 API Key로 /api/external/wp/health를 호출합니다. 키 변경 후에는 먼저 저장 → 테스트 순서로 진행하세요.', 'ivy-support-ticket' ); ?>
						</p>
					</td>
				</tr>
			</table>

		<?php elseif ( $active_tab === 'users' ) : ?>
			<p class="description ivy-st-section-help">
				<?php esc_html_e( '아래 목록에서 티켓을 발행할 수 있는 워드프레스 사용자를 선택하세요. 활성화 시 administrator·editor 역할의 모든 사용자가 자동 등록됩니다.', 'ivy-support-ticket' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '허용된 사용자', 'ivy-support-ticket' ); ?></th>
					<td>
						<select name="allowed_user_ids[]" multiple class="ivy-st-user-select" size="10" style="min-width:480px">
							<?php foreach ( $users as $u ) : ?>
								<?php
								$selected = in_array( (int) $u->ID, array_map( 'absint', (array) $settings['allowed_user_ids'] ), true );
								$pm_id    = (string) get_user_meta( (int) $u->ID, IVY_ST_USERMETA_PM_USER_ID, true );
								$roles    = wp_get_user_roles_for_select( (int) $u->ID );
								$label    = sprintf( '%s — %s [%s]', $u->display_name, $u->user_email, $roles );
								if ( $pm_id !== '' ) {
									$label .= ' ✓';
								}
								?>
								<option value="<?php echo (int) $u->ID; ?>" <?php selected( $selected ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Ctrl(또는 Cmd) 클릭으로 다중 선택. ✓ 표시는 pm 시스템에 매핑이 완료된 사용자입니다.', 'ivy-support-ticket' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '자동 등록', 'ivy-support-ticket' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_enroll_admin_editor" value="1"
							       <?php checked( ! empty( $settings['auto_enroll_admin_editor'] ) ); ?> />
							<?php esc_html_e( '신규 또는 권한이 administrator/editor로 변경된 사용자를 자동으로 허용 목록에 추가', 'ivy-support-ticket' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '기본값으로 재설정', 'ivy-support-ticket' ); ?></th>
					<td>
						<button type="submit" name="ivy_st_reset_to_defaults" value="1"
						        class="button"
						        onclick="return confirm('<?php echo esc_js( __( '허용 사용자 목록을 administrator + editor 전체로 재설정하시겠습니까?', 'ivy-support-ticket' ) ); ?>');">
							<?php esc_html_e( '관리자/편집자 전체로 초기화', 'ivy-support-ticket' ); ?>
						</button>
					</td>
				</tr>
			</table>

		<?php elseif ( $active_tab === 'info' ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '플러그인 버전', 'ivy-support-ticket' ); ?></th>
					<td><code><?php echo esc_html( IVY_ST_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '저장소', 'ivy-support-ticket' ); ?></th>
					<td>
						<a href="https://github.com/ivystation/ivy-support-ticket-for-wp" target="_blank" rel="noopener noreferrer">
							ivystation/ivy-support-ticket-for-wp
						</a>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ivy-st-debug"><?php esc_html_e( '디버그 로그', 'ivy-support-ticket' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" id="ivy-st-debug" name="debug" value="1"
							       <?php checked( ! empty( $settings['debug'] ) ); ?> />
							<?php esc_html_e( 'wp-content/uploads/ivy-support-ticket-debug.log에 API 호출 결과 기록 (API Key 마스킹)', 'ivy-support-ticket' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '첨부 업로드 (R2 CORS)', 'ivy-support-ticket' ); ?></th>
					<td>
						<p class="description">
							<?php esc_html_e( '브라우저가 Cloudflare R2에 직접 PUT으로 첨부를 업로드하므로, R2 버킷의 CORS 정책에 이 사이트의 도메인을 등록해야 합니다.', 'ivy-support-ticket' ); ?>
						</p>
						<details class="ivy-st-cors-help">
							<summary><?php esc_html_e( '권장 CORS 규칙 보기', 'ivy-support-ticket' ); ?></summary>
							<pre class="ivy-st-cors-pre"><code><?php
								$origin = home_url();
								echo esc_html(
									wp_json_encode(
										array(
											array(
												'AllowedOrigins' => array( $origin ),
												'AllowedMethods' => array( 'PUT', 'GET', 'HEAD' ),
												'AllowedHeaders' => array( '*' ),
												'ExposeHeaders'  => array( 'ETag' ),
												'MaxAgeSeconds'  => 3600,
											),
										),
										JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
									)
								);
							?></code></pre>
							<p class="description">
								<?php esc_html_e( '여러 사이트에 플러그인을 설치한 경우, 각 사이트의 origin을 AllowedOrigins 배열에 모두 추가하거나 와일드카드를 사용하세요.', 'ivy-support-ticket' ); ?>
							</p>
						</details>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php submit_button( __( '설정 저장', 'ivy-support-ticket' ) ); ?>
	</form>
</div>
