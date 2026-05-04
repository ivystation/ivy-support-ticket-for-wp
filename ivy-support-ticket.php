<?php
/**
 * Plugin Name:       Ivy Support Ticket for WordPress
 * Plugin URI:        https://github.com/ivystation/ivy-support-ticket-for-wp
 * Description:       워드프레스 어드민에서 직접 1:1 지원 티켓을 발행·관리하는 플러그인. pm.ivynet.co.kr API 연동, 자체 DB 미사용.
 * Version:           0.2.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            ivynet
 * Author URI:        https://ivynet.co.kr
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ivy-support-ticket
 * Domain Path:       /languages
 *
 * @package IvySupportTicket
 */

defined( 'ABSPATH' ) || exit;

// 상수: 다른 모든 클래스가 참조하는 단일 진입점.
define( 'IVY_ST_VERSION', '0.2.2' );
define( 'IVY_ST_PLUGIN_FILE', __FILE__ );
define( 'IVY_ST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IVY_ST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IVY_ST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'IVY_ST_TEXT_DOMAIN', 'ivy-support-ticket' );

// 옵션·메타 키 — 한 곳에서만 정의해 오타·중복을 방지한다.
define( 'IVY_ST_OPT_SETTINGS', 'ivy_st_settings' );
define( 'IVY_ST_USERMETA_PM_USER_ID', 'ivy_st_pm_user_id' );

require_once IVY_ST_PLUGIN_DIR . 'includes/class-settings.php';
require_once IVY_ST_PLUGIN_DIR . 'includes/class-sso.php';
require_once IVY_ST_PLUGIN_DIR . 'includes/class-labels.php';
require_once IVY_ST_PLUGIN_DIR . 'includes/class-api-client.php';
require_once IVY_ST_PLUGIN_DIR . 'includes/class-user-mapping.php';
require_once IVY_ST_PLUGIN_DIR . 'includes/class-updater.php';
require_once IVY_ST_PLUGIN_DIR . 'admin/class-ticket-list-table.php';
require_once IVY_ST_PLUGIN_DIR . 'admin/class-admin-pages.php';
require_once IVY_ST_PLUGIN_DIR . 'includes/class-plugin.php';

// 활성화·비활성화 훅. 본문은 Plugin::activate / deactivate 에 위임.
register_activation_hook( __FILE__, array( '\\IvyST\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\IvyST\\Plugin', 'deactivate' ) );

// 부트스트랩 — 모든 훅 등록은 Plugin::boot 안에서 이루어진다.
add_action( 'plugins_loaded', array( '\\IvyST\\Plugin', 'boot' ) );
