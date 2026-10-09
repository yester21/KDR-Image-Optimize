<?php
/**
 * 이미지 변환 엔진.
 *
 * @package KDR_Image_Optimize
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 업로드된 이미지를 리사이즈·압축하고, 설정에 따라 WebP 로 변환한다.
 */
class KDR_IO_Converter {

	/**
	 * 재귀 호출 방지를 위한 처리 중 첨부 ID 목록.
	 *
	 * @var array
	 */
	private static $working = array();

	/**
	 * 후크 등록.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_generate_metadata' ), 999, 2 );
	}

	/**
	 * 자동 변환 후크를 잠시 해제한다. (일괄 변환 시 이중 압축 방지)
	 *
	 * @return void
	 */
	public static function suspend() {
		remove_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_generate_metadata' ), 999 );
	}

	/**
	 * 해제한 후크를 다시 등록한다.
	 *
	 * @return void
	 */
	public static function resume() {
		remove_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_generate_metadata' ), 999 );
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'on_generate_metadata' ), 999, 2 );
	}

	/**
	 * 설정 기본값.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'max_width'       => 900,
			'quality'         => 80,
			'convert_webp'    => 1,
			'delete_original' => 1,
		);
	}

	/**
	 * 현재 설정.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( KDR_IO_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * 이 크기 미만의 파일은 건드리지 않는다. (아이콘·로고 보호)
	 *
	 * @return int
	 */
	public static function min_bytes() {
		/**
		 * 최소 처리 크기(바이트)를 변경한다.
		 *
		 * @param int $bytes 기본 20480 (20KB)
		 */
		return (int) apply_filters( 'kdr_io_min_bytes', 20480 );
	}

	/**
	 * 서버 이미지 엔진이 WebP 저장을 지원하는지 확인한다.
	 *
	 * @return bool
	 */
	public static function supports_webp() {
		if ( ! function_exists( 'wp_image_editor_supports' ) ) {
			return false;
		}

		return (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * 사용 중인 이미지 엔진 이름을 반환한다.
	 *
	 * @return string
	 */
	public static function editor_name() {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			return 'Imagick';
		}
		if ( extension_loaded( 'gd' ) ) {
			return 'GD';
		}

		return '';
	}

	/**
	 * 백업 폴더의 절대 경로.
	 *
	 * @return string
	 */
	public static function backup_dir() {
		$uploads = wp_get_upload_dir();

		return empty( $uploads['basedir'] ) ? '' : trailingslashit( $uploads['basedir'] ) . 'kdr-originals';
	}

	/**
	 * 관리자 화면에서 쓸 환경 점검 정보.
	 *
	 * @return array
	 */
	public static function environment() {
		$uploads = wp_get_upload_dir();
		$backup  = self::backup_dir();
		$stats   = self::dir_stats( $backup );

		return array(
			'editor'        => self::editor_name(),
			'webp'          => self::supports_webp(),
			'upload_dir'    => isset( $uploads['basedir'] ) ? $uploads['basedir'] : '',
			'upload_url'    => isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '',
			'writable'      => ! empty( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] ),
			'backup_dir'    => $backup,
			'backup_url'    => ! empty( $uploads['baseurl'] ) ? trailingslashit( $uploads['baseurl'] ) . 'kdr-originals' : '',
			'backup_count'  => $stats['count'],
			'backup_bytes'  => $stats['bytes'],
			'backup_exists' => $backup && is_dir( $backup ),
		);
	}

	/**
	 * 디렉터리 안의 파일 개수와 총 용량을 구한다.
	 *
	 * @param string $dir 절대 경로.
	 * @return array count, bytes
	 */
	public static function dir_stats( $dir ) {
		$stats = array(
			'count' => 0,
			'bytes' => 0,
		);

		if ( ! $dir || ! is_dir( $dir ) ) {
			return $stats;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $iterator as $item ) {
				if ( $item->isFile() ) {
					++$stats['count'];
					$stats['bytes'] += (int) $item->getSize();
				}
			}
		} catch ( Throwable $e ) {
			return $stats;
		}

		return $stats;
	}

	/**
	 * 업로드 직후 실행되는 필터.
	 *
	 * @param array $metadata      첨부 메타데이터.
	 * @param int   $attachment_id 첨부 ID.
	 * @return array
	 */
	public static function on_generate_metadata( $metadata, $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( isset( self::$working[ $attachment_id ] ) ) {
			return $metadata;
		}

		self::$working[ $attachment_id ] = true;

		try {
			$result = self::maybe_convert( $attachment_id );

			if ( is_wp_error( $result ) ) {
				self::log( sprintf( '첨부 #%d 변환 실패: %s', $attachment_id, $result->get_error_message() ) );

				return $metadata;
			}

			if ( true === $result ) {
				$file = get_attached_file( $attachment_id );
				$new  = wp_generate_attachment_metadata( $attachment_id, $file );

				if ( ! is_wp_error( $new ) && is_array( $new ) ) {
					wp_update_attachment_metadata( $attachment_id, $new );

					return $new;
				}
			}
		} catch ( Exception $e ) {
			self::log( sprintf( '첨부 #%d 처리 중 예외: %s', $attachment_id, $e->getMessage() ) );
		} catch ( Error $e ) {
			self::log( sprintf( '첨부 #%d 처리 중 오류: %s', $attachment_id, $e->getMessage() ) );
		} finally {
			unset( self::$working[ $attachment_id ] );
		}

		return $metadata;
	}

	/**
	 * 첨부 1건을 변환한다.
	 *
	 * @param int $attachment_id 첨부 ID.
	 * @return true|false|WP_Error 변환 성공 시 true, 대상 아님/효과 없음이면 false, 실패 시 WP_Error.
	 */
	public static function maybe_convert( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$settings      = self::get_settings();

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_file( $file ) ) {
			return false;
		}

		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'png', 'jpg', 'jpeg' ), true ) ) {
			return false;
		}

		if ( 'image/webp' === get_post_mime_type( $attachment_id ) ) {
			return false;
		}

		$original_size = (int) filesize( $file );
		if ( $original_size <= 0 || $original_size < self::min_bytes() ) {
			return false;
		}

		$convert_webp = ! empty( $settings['convert_webp'] );

		if ( $convert_webp && ! self::supports_webp() ) {
			return new WP_Error(
				'kdr_io_no_webp',
				__( '서버 이미지 엔진이 WebP 를 지원하지 않아 변환을 건너뛰었습니다.', 'kdr-image-optimize' )
			);
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$max_width = max( 1, (int) $settings['max_width'] );
		$size      = $editor->get_size();

		if ( is_array( $size ) && ! empty( $size['width'] ) && (int) $size['width'] > $max_width ) {
			$resized = $editor->resize( $max_width, 0, false );
			if ( is_wp_error( $resized ) ) {
				return $resized;
			}
		}

		$editor->set_quality( max( 1, min( 100, (int) $settings['quality'] ) ) );

		if ( $convert_webp ) {
			$target_ext  = 'webp';
			$target_mime = 'image/webp';
		} else {
			$target_ext  = $ext;
			$target_mime = ( 'png' === $ext ) ? 'image/png' : 'image/jpeg';
		}

		$target = preg_replace( '/\.[^.\/\\\\]+$/', '.' . $target_ext, $file );
		$same   = ( $target === $file );
		$delete = ! empty( $settings['delete_original'] );

		/*
		 * 확장자를 바꾸지 않는 경우(WebP 변환 없음)에는 원본을 덮어쓰기 전에
		 * 임시 파일에 먼저 저장한다. 임시 파일도 대상 확장자로 끝나야
		 * WP_Image_Editor 가 포맷을 올바르게 판별한다.
		 */
		$write_to = $same
			? dirname( $file ) . '/' . pathinfo( $file, PATHINFO_FILENAME ) . '.kdr-tmp.' . $target_ext
			: $target;

		if ( file_exists( $write_to ) ) {
			@unlink( $write_to );
		}

		$saved = $editor->save( $write_to, $target_mime );

		if ( is_wp_error( $saved ) ) {
			if ( file_exists( $write_to ) ) {
				@unlink( $write_to );
			}

			return $saved;
		}

		if ( ! is_file( $write_to ) ) {
			return new WP_Error( 'kdr_io_save', __( '변환한 파일을 저장하지 못했습니다.', 'kdr-image-optimize' ) );
		}

		$new_size = (int) filesize( $write_to );

		// 결과가 원본보다 크거나 같으면 최적화 효과가 없으므로 원본을 유지한다.
		if ( $new_size >= $original_size ) {
			@unlink( $write_to );

			return false;
		}

		$backup_file = '';

		// 원본 삭제를 끈 경우에는 언제나 백업 폴더로 옮겨 보관한다.
		if ( ! $delete ) {
			$backup_file = self::backup_original( $file );

			if ( ! $backup_file ) {
				@unlink( $write_to );

				return new WP_Error(
					'kdr_io_backup',
					__( '원본을 백업 폴더로 옮기지 못해 변환을 취소했습니다. 업로드 폴더의 쓰기 권한을 확인하세요.', 'kdr-image-optimize' )
				);
			}
		}

		if ( $same ) {
			// 같은 포맷으로 압축: 임시 파일을 원본 자리로 옮긴다.
			if ( ! @rename( $write_to, $file ) ) {
				@unlink( $write_to );

				return new WP_Error( 'kdr_io_replace', __( '원본 파일을 교체하지 못했습니다.', 'kdr-image-optimize' ) );
			}

			$new_file = $file;
		} else {
			// WebP 변환: 새 파일을 서빙하고, 원본 삭제가 켜져 있으면 원본을 지운다.
			$new_file = $target;

			if ( $delete ) {
				@unlink( $file );
			}
		}

		update_post_meta( $attachment_id, '_wp_attached_file', _wp_relative_upload_path( $new_file ) );

		if ( $backup_file ) {
			update_post_meta( $attachment_id, '_kdr_io_original_file', _wp_relative_upload_path( $backup_file ) );
		}

		// 첨부의 MIME 타입을 실제 파일과 일치시킨다.
		wp_update_post(
			array(
				'ID'             => $attachment_id,
				'post_mime_type' => $target_mime,
			)
		);

		self::delete_stale_variants( $file );

		return true;
	}

	/**
	 * 원본 파일을 백업 폴더로 옮긴다.
	 *
	 * @param string $file 원본 절대 경로.
	 * @return string|false 옮겨진 절대 경로, 실패 시 false.
	 */
	private static function backup_original( $file ) {
		$backup_root = self::backup_dir();
		if ( ! $backup_root ) {
			return false;
		}

		$uploads = wp_get_upload_dir();
		$rel     = ltrim( str_replace( $uploads['basedir'], '', $file ), '/\\' );
		$dest    = trailingslashit( $backup_root ) . $rel;

		if ( ! wp_mkdir_p( dirname( $dest ) ) ) {
			return false;
		}

		if ( file_exists( $dest ) ) {
			$dest = preg_replace( '/(\.[^.\/\\\\]+)$/', '-kdr-' . gmdate( 'YmdHis' ) . '$1', $dest );
		}

		if ( ! @rename( $file, $dest ) ) {
			return false;
		}

		self::protect_dir( $backup_root );

		return $dest;
	}

	/**
	 * 백업 폴더에 빈 index.php 를 만들어 목록 노출을 막는다.
	 *
	 * @param string $dir 디렉터리 절대 경로.
	 * @return void
	 */
	private static function protect_dir( $dir ) {
		$index = trailingslashit( $dir ) . 'index.php';
		if ( file_exists( $index ) ) {
			return;
		}

		@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	/**
	 * 변환 전에 만들어진 구 포맷 썸네일과 -scaled 파일을 정리한다.
	 *
	 * 파일명 접두사만 보고 지우면 같은 접두사를 쓰는 다른 첨부파일의 원본까지
	 * 삭제되므로, 반드시 "이름-WxH.확장자" 규격만 대상으로 한다.
	 *
	 * @param string $file 기준이 되는 파일 절대 경로.
	 * @return void
	 */
	private static function delete_stale_variants( $file ) {
		$dir  = dirname( $file );
		$base = pathinfo( $file, PATHINFO_FILENAME );

		if ( '' === $base ) {
			return;
		}

		// glob 메타문자 이스케이프.
		$glob_base = str_replace(
			array( '\\', '*', '?', '[', ']' ),
			array( '\\\\', '\\*', '\\?', '\\[', '\\]' ),
			$base
		);

		$size_pattern = '/^' . preg_quote( $base, '/' ) . '-\d+x\d+\.(png|jpe?g|webp)$/i';

		$scaled = array(
			$dir . '/' . $base . '-scaled.png',
			$dir . '/' . $base . '-scaled.jpg',
			$dir . '/' . $base . '-scaled.jpeg',
		);

		$candidates = array_merge( (array) glob( $dir . '/' . $glob_base . '-*' ), $scaled );

		foreach ( $candidates as $old ) {
			if ( ! is_file( $old ) ) {
				continue;
			}

			if ( in_array( $old, $scaled, true ) || preg_match( $size_pattern, basename( $old ) ) ) {
				@unlink( $old );
			}
		}
	}

	/**
	 * WP_DEBUG 가 켜져 있을 때만 기록한다.
	 *
	 * @param string $message 메시지.
	 * @return void
	 */
	public static function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[KDR 이미지 최적화] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * 파일 경로에서 첨부 파일명만 뽑아낸다. (CLI 표시용)
	 *
	 * @param string $file 절대 경로.
	 * @return string
	 */
	public static function short_path( $file ) {
		$uploads = wp_get_upload_dir();

		return ltrim( str_replace( $uploads['basedir'], '', (string) $file ), '/\\' );
	}
}
