<?php
/**
 * Plugin Name:       KDR 이미지 최적화
 * Plugin URI:        https://kimderi.net
 * Description:       업로드한 이미지를 지정한 최대 너비로 리사이즈하고 지정한 압축률로 다시 저장합니다. WebP 변환 여부와 원본 삭제 여부를 각각 선택할 수 있습니다.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            김대리닷넷
 * Author URI:        https://kimderi.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdr-image-optimize
 * Domain Path:       /languages
 *
 * @package KDR_Image_Optimize
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDR_IO_VERSION', '1.0.0' );
define( 'KDR_IO_FILE', __FILE__ );
define( 'KDR_IO_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDR_IO_URL', plugin_dir_url( __FILE__ ) );

/**
 * 설정을 저장하는 옵션 키.
 *
 * @var string
 */
define( 'KDR_IO_OPTION', 'kdr_image_optimize_settings' );

require_once KDR_IO_DIR . 'includes/class-kdr-io-converter.php';
require_once KDR_IO_DIR . 'includes/class-kdr-io-settings.php';

KDR_IO_Converter::init();
KDR_IO_Settings::init();

/**
 * 번역 파일을 로드한다.
 *
 * @return void
 */
function kdr_io_load_textdomain() {
	load_plugin_textdomain( 'kdr-image-optimize', false, dirname( plugin_basename( KDR_IO_FILE ) ) . '/languages' );
}
add_action( 'init', 'kdr_io_load_textdomain' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once KDR_IO_DIR . 'includes/class-kdr-io-cli.php';
	WP_CLI::add_command( 'kdr-image-optimize', 'KDR_IO_CLI' );
}
