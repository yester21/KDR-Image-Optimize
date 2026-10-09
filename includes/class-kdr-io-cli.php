<?php
/**
 * WP-CLI 명령.
 *
 * @package KDR_Image_Optimize
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 기존 첨부 이미지를 일괄 변환한다.
 */
class KDR_IO_CLI {

	/**
	 * 이미 올라가 있는 PNG·JPG 첨부를 일괄 변환한다.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : 실제로 변환하지 않고 대상만 출력한다.
	 *
	 * [--limit=<number>]
	 * : 처리할 최대 개수.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kdr-image-optimize convert-all --dry-run
	 *     wp kdr-image-optimize convert-all
	 *
	 * @param array $args       위치 인자.
	 * @param array $assoc_args 옵션 인자.
	 * @return void
	 */
	public function convert_all( $args, $assoc_args ) {
		$dry   = ! empty( $assoc_args['dry-run'] );
		$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 0;

		$settings = KDR_IO_Converter::get_settings();

		if ( ! empty( $settings['convert_webp'] ) && ! KDR_IO_Converter::supports_webp() ) {
			WP_CLI::error( '이 PHP 에는 WebP 를 저장할 수 있는 이미지 엔진(GD/Imagick)이 없습니다. 웹에서 쓰는 PHP 로 실행하세요.' );
		}

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				// 배열로 넘겨야 image/jpeg 가 정확히 매칭된다.
				'post_mime_type' => array( 'image/png', 'image/jpeg' ),
				'posts_per_page' => $limit > 0 ? $limit : -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$total = count( $ids );
		WP_CLI::log( sprintf( '대상 첨부: %d개 (WebP %s / 최대 %dpx / 품질 %d / 원본 %s)', $total, ! empty( $settings['convert_webp'] ) ? 'ON' : 'OFF', (int) $settings['max_width'], (int) $settings['quality'], ! empty( $settings['delete_original'] ) ? '삭제' : '보관' ) );

		if ( 0 === $total ) {
			WP_CLI::success( '변환할 첨부가 없습니다.' );

			return;
		}

		$ok    = 0;
		$skip  = 0;
		$fail  = 0;
		$progress = WP_CLI\Utils\make_progress_bar( '변환 중', $total );

		KDR_IO_Converter::suspend();

		try {
			foreach ( $ids as $id ) {
				$before = KDR_IO_Converter::short_path( get_attached_file( $id ) );

				if ( $dry ) {
					WP_CLI::log( sprintf( '[dry-run] #%d %s', $id, $before ) );
					++$skip;
					$progress->tick();
					continue;
				}

				$result = KDR_IO_Converter::maybe_convert( $id );

				if ( is_wp_error( $result ) ) {
					++$fail;
					WP_CLI::warning( sprintf( '#%d %s → %s', $id, $before, $result->get_error_message() ) );
				} elseif ( true === $result ) {
					$file = get_attached_file( $id );
					$meta = wp_generate_attachment_metadata( $id, $file );
					if ( ! is_wp_error( $meta ) ) {
						wp_update_attachment_metadata( $id, $meta );
					}

					++$ok;
					WP_CLI::log( sprintf( 'OK #%d %s → %s', $id, $before, KDR_IO_Converter::short_path( $file ) ) );
				} else {
					++$skip;
				}

				$progress->tick();
			}
		} finally {
			KDR_IO_Converter::resume();
			$progress->finish();
		}

		if ( $dry ) {
			WP_CLI::success( sprintf( 'dry-run 완료. 대상 %d개.', $total ) );

			return;
		}

		WP_CLI::success( sprintf( '변환 %d건 / 제외 %d건 / 실패 %d건', $ok, $skip, $fail ) );
	}

	/**
	 * 실제 파일은 .webp 인데 MIME 타입이 옛 포맷으로 남아 있는 첨부를 바로잡는다.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : 실제로 바꾸지 않고 대상만 출력한다.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kdr-image-optimize fix-mime --dry-run
	 *     wp kdr-image-optimize fix-mime
	 *
	 * @param array $args       위치 인자.
	 * @param array $assoc_args 옵션 인자.
	 * @return void
	 */
	public function fix_mime( $args, $assoc_args ) {
		$dry = ! empty( $assoc_args['dry-run'] );

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/png', 'image/jpeg', 'image/gif' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$targets = array();

		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( $file && preg_match( '/\.webp$/i', $file ) && 'image/webp' !== get_post_mime_type( $id ) ) {
				$targets[] = $id;
			}
		}

		if ( empty( $targets ) ) {
			WP_CLI::success( 'MIME 타입이 어긋난 첨부가 없습니다.' );

			return;
		}

		WP_CLI::log( sprintf( '대상 %d개', count( $targets ) ) );

		if ( $dry ) {
			foreach ( $targets as $id ) {
				WP_CLI::log( sprintf( '[dry-run] #%d %s', $id, KDR_IO_Converter::short_path( get_attached_file( $id ) ) ) );
			}
			WP_CLI::success( 'dry-run 완료.' );

			return;
		}

		KDR_IO_Converter::suspend();
		$fixed = 0;

		try {
			foreach ( $targets as $id ) {
				wp_update_post(
					array(
						'ID'             => $id,
						'post_mime_type' => 'image/webp',
					)
				);

				$file = get_attached_file( $id );
				$meta = wp_generate_attachment_metadata( $id, $file );
				if ( ! is_wp_error( $meta ) ) {
					wp_update_attachment_metadata( $id, $meta );
				}

				++$fixed;
				WP_CLI::log( sprintf( 'OK #%d %s', $id, KDR_IO_Converter::short_path( $file ) ) );
			}
		} finally {
			KDR_IO_Converter::resume();
		}

		WP_CLI::success( sprintf( 'MIME 타입 %d건 수정', $fixed ) );
	}
}
