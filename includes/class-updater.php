<?php
/**
 * Updater: GitHub Releases 기반 자동 업데이트 (plugin-update-checker v5).
 *
 * 동작:
 *   - tag push 시 GitHub Actions가 zip을 빌드해 Release asset에 첨부 → PUC가 그 zip을 사용.
 *   - WP 어드민 → 플러그인 → 업데이트 알림 배지 → 클릭 한 번으로 갱신.
 *
 * @package IvySupportTicket
 */

namespace IvyST;

defined( 'ABSPATH' ) || exit;

class Updater {

	/** Plugin::boot에서 호출. */
	public static function init(): void {
		$loader = IVY_ST_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $loader ) ) {
			// vendor가 없는 비공식 배포본에서는 자동 업데이트를 건너뛴다 (수동 zip 설치 가능).
			return;
		}
		require_once $loader;

		// PUC v5의 Factory 클래스. 네임스페이스가 v5p6 등 마이너에 따라 달라질 수 있어 동적 호출.
		$factory = '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
		if ( ! class_exists( $factory ) ) {
			return;
		}

		$update_checker = $factory::buildUpdateChecker(
			'https://github.com/ivystation/ivy-support-ticket-for-wp/',
			IVY_ST_PLUGIN_FILE,
			'ivy-support-ticket-for-wp'
		);

		// Releases 자산을 우선 사용 (Actions가 만든 zip).
		$update_checker->setBranch( 'main' );
		if ( method_exists( $update_checker, 'getVcsApi' ) ) {
			$vcs = $update_checker->getVcsApi();
			if ( $vcs && method_exists( $vcs, 'enableReleaseAssets' ) ) {
				$vcs->enableReleaseAssets();
			}
		}
	}
}
