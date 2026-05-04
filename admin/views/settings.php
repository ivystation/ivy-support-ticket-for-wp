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
	'sso'        => __( 'SSO', 'ivy-support-ticket' ),
	'users'      => __( '사용자 매핑', 'ivy-support-ticket' ),
	'info'       => __( '정보', 'ivy-support-ticket' ),
);
$has_sso_secret = ! empty( $settings['sso_secret'] );

// 사용자 매핑 탭에서 표시할 "등록된 사용자" 목록.
// AJAX로 추가/해지될 때마다 JS가 행을 즉시 갱신하지만,
// 페이지 첫 로드 시점에는 서버측에서 한 번 렌더링한다.
$enrolled_users = array();
$enrolled_ids   = array_map( 'absint', (array) $settings['allowed_user_ids'] );
foreach ( $enrolled_ids as $uid ) {
	$wp_u = get_userdata( $uid );
	if ( ! $wp_u ) {
		continue;
	}
	$enrolled_users[] = \IvyST\AdminPages::format_user_for_ui( $wp_u );
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

		<?php elseif ( $active_tab === 'sso' ) : ?>
			<p class="description ivy-st-section-help">
				<?php esc_html_e( 'SSO를 활성화하면 메뉴 클릭 시 아래 티켓 URL로 자동 로그인됩니다. 시크릿 키는 ticket.ivynet.co.kr 서버의 SSO_SECRET 환경변수와 동일하게 설정하세요.', 'ivy-support-ticket' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ivy-st-ticket-url"><?php esc_html_e( '티켓 포털 URL', 'ivy-support-ticket' ); ?></label></th>
					<td>
						<input type="url" id="ivy-st-ticket-url" name="ticket_url"
						       value="<?php echo esc_attr( (string) $settings['ticket_url'] ); ?>"
						       class="regular-text" />
						<p class="description"><?php esc_html_e( '기본값: https://ticket.ivynet.co.kr', 'ivy-support-ticket' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ivy-st-sso-secret"><?php esc_html_e( 'SSO 시크릿 키', 'ivy-support-ticket' ); ?></label></th>
					<td>
						<input type="text" id="ivy-st-sso-secret" name="sso_secret"
						       value=""
						       placeholder="<?php echo $has_sso_secret ? esc_attr__( '저장된 키 있음 — 변경 시에만 입력', 'ivy-support-ticket' ) : '32자 이상의 랜덤 문자열'; ?>"
						       class="regular-text" autocomplete="off" />
						<p class="description">
							<?php
							if ( $has_sso_secret ) {
								esc_html_e( '저장된 시크릿이 있습니다. 변경하려면 새 값을 입력하세요. 비워 두면 기존 값 유지.', 'ivy-support-ticket' );
							} else {
								esc_html_e( 'pm 서버의 SSO_SECRET 환경변수와 동일한 값을 입력하세요. 32자 이상 권장.', 'ivy-support-ticket' );
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'SSO 상태', 'ivy-support-ticket' ); ?></th>
					<td>
						<?php if ( \IvyST\Sso::is_configured() ) : ?>
							<span style="color:#46b450;">&#10003; <?php esc_html_e( '활성화됨 — 메뉴 클릭 시 ticket 포털로 새 탭이 열립니다.', 'ivy-support-ticket' ); ?></span>
						<?php else : ?>
							<span style="color:#999;"><?php esc_html_e( '비활성화됨 — 티켓 포털 URL과 SSO 시크릿 키를 모두 입력하면 활성화됩니다.', 'ivy-support-ticket' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

		<?php elseif ( $active_tab === 'users' ) : ?>
			<p class="description ivy-st-section-help">
				<?php esc_html_e( '왼쪽 목록에서 사용자를 클릭해 선택한 뒤 → 버튼으로 등록하거나, 오른쪽 목록에서 선택 후 ← 버튼으로 해지하세요.', 'ivy-support-ticket' ); ?>
			</p>

			<div class="ivy-st-dual-list-wrap">
				<div class="ivy-st-dual-panel">
					<input type="search" id="ivy-st-left-search" class="ivy-st-dual-search"
					       placeholder="<?php esc_attr_e( '이메일 / 이름 / 아이디 검색', 'ivy-support-ticket' ); ?>" />
					<ul id="ivy-st-left-list" class="ivy-st-dual-list">
						<li class="ivy-st-dual-empty"><?php esc_html_e( '로딩 중...', 'ivy-support-ticket' ); ?></li>
					</ul>
				</div>

				<div class="ivy-st-dual-arrows">
					<button type="button" id="ivy-st-move-right" class="button ivy-st-arrow-btn"
					        title="<?php esc_attr_e( '선택 사용자 등록', 'ivy-support-ticket' ); ?>">&#10145;</button>
					<button type="button" id="ivy-st-move-left" class="button ivy-st-arrow-btn"
					        title="<?php esc_attr_e( '선택 사용자 해지', 'ivy-support-ticket' ); ?>">&#8592;</button>
				</div>

				<div class="ivy-st-dual-panel">
					<input type="search" id="ivy-st-right-search" class="ivy-st-dual-search"
					       placeholder="<?php esc_attr_e( '등록된 사용자 검색', 'ivy-support-ticket' ); ?>" />
					<ul id="ivy-st-right-list" class="ivy-st-dual-list">
						<?php if ( empty( $enrolled_users ) ) : ?>
							<li class="ivy-st-dual-empty"><?php esc_html_e( '등록된 사용자가 없습니다.', 'ivy-support-ticket' ); ?></li>
						<?php else : ?>
							<?php foreach ( $enrolled_users as $u ) : ?>
								<li class="ivy-st-dual-item"
								    data-user-id="<?php echo (int) $u['id']; ?>"
								    data-label="<?php echo esc_attr( $u['display_name'] . ' ' . $u['email'] ); ?>">
									<span class="ivy-st-dual-name"><?php echo esc_html( $u['display_name'] ); ?></span><span class="ivy-st-dual-email">(<?php echo esc_html( $u['email'] ); ?>)</span><?php if ( ! empty( $u['roles'] ) ) : ?><span class="ivy-st-dual-role"> - <?php echo esc_html( implode( ', ', $u['roles'] ) ); ?></span><?php endif; ?>
								</li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>
				</div>
			</div>

			<p class="ivy-st-dual-page-info">
				<button type="button" id="ivy-st-left-prev" class="button button-small" disabled>&#8249; <?php esc_html_e( '이전', 'ivy-support-ticket' ); ?></button>
				<span id="ivy-st-page-info-text">Page 1 of 1</span>
				<button type="button" id="ivy-st-left-next" class="button button-small" disabled><?php esc_html_e( '다음', 'ivy-support-ticket' ); ?> &#8250;</button>
			</p>

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

		<?php
		// users 탭은 폼 submit 항목이 없다(검색·추가·해지는 모두 AJAX). 저장 버튼 숨김.
		if ( ! in_array( $active_tab, array( 'users' ), true ) ) {
			submit_button( __( '설정 저장', 'ivy-support-ticket' ) );
		}
		?>
	</form>
</div>
