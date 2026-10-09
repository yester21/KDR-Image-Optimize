<?php
/**
 * 플러그인 삭제 시 실행된다.
 *
 * 설정과 플러그인이 남긴 메타 정보만 지운다.
 * uploads/kdr-originals/ 에 보관된 원본 이미지는 사용자의 자산이므로 지우지 않는다.
 *
 * @package KDR_Image_Optimize
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'kdr_image_optimize_settings' );

global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_kdr_io_original_file' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
