<?php
/**
 * 관리자 설정 화면.
 *
 * @package KDR_Image_Optimize
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 설정 페이지와 옵션 등록을 담당한다.
 */
class KDR_IO_Settings {

	/**
	 * 설정 그룹 이름.
	 *
	 * @var string
	 */
	const GROUP = 'kdr_io_group';

	/**
	 * 페이지 슬러그.
	 *
	 * @var string
	 */
	const PAGE = 'kdr-image-optimize';

	/**
	 * 후크 등록.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KDR_IO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * 설정 메뉴에 페이지를 추가한다.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_options_page(
			__( 'KDR 이미지 최적화', 'kdr-image-optimize' ),
			__( 'KDR 이미지 최적화', 'kdr-image-optimize' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * 플러그인 목록에 설정 링크를 붙인다.
	 *
	 * @param array $links 기존 링크.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( '설정', 'kdr-image-optimize' ) . '</a>'
		);

		return $links;
	}

	/**
	 * 옵션과 필드를 등록한다.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			KDR_IO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => KDR_IO_Converter::defaults(),
			)
		);

		add_settings_section(
			'kdr_io_main',
			__( '이미지 처리 설정', 'kdr-image-optimize' ),
			array( __CLASS__, 'section_intro' ),
			self::PAGE
		);

		add_settings_field(
			'kdr_io_max_width',
			__( '최대 이미지 너비', 'kdr-image-optimize' ),
			array( __CLASS__, 'field_max_width' ),
			self::PAGE,
			'kdr_io_main'
		);

		add_settings_field(
			'kdr_io_quality',
			__( '압축률(품질)', 'kdr-image-optimize' ),
			array( __CLASS__, 'field_quality' ),
			self::PAGE,
			'kdr_io_main'
		);

		add_settings_field(
			'kdr_io_convert_webp',
			__( 'WebP 변환', 'kdr-image-optimize' ),
			array( __CLASS__, 'field_convert_webp' ),
			self::PAGE,
			'kdr_io_main'
		);

		add_settings_field(
			'kdr_io_delete_original',
			__( '원본 삭제', 'kdr-image-optimize' ),
			array( __CLASS__, 'field_delete_original' ),
			self::PAGE,
			'kdr_io_main'
		);
	}

	/**
	 * 값을 정리한다.
	 *
	 * @param mixed $input 사용자 입력.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = KDR_IO_Converter::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		if ( isset( $input['max_width'] ) && '' !== trim( (string) $input['max_width'] ) ) {
			$output['max_width'] = max( 100, min( 5000, absint( $input['max_width'] ) ) );
		} else {
			$output['max_width'] = $defaults['max_width'];
		}

		if ( isset( $input['quality'] ) && '' !== trim( (string) $input['quality'] ) ) {
			$output['quality'] = max( 10, min( 100, absint( $input['quality'] ) ) );
		} else {
			$output['quality'] = $defaults['quality'];
		}

		$output['convert_webp']    = empty( $input['convert_webp'] ) ? 0 : 1;
		$output['delete_original'] = empty( $input['delete_original'] ) ? 0 : 1;

		return $output;
	}

	/**
	 * 섹션 설명.
	 *
	 * @return void
	 */
	public static function section_intro() {
		echo '<p>' . esc_html__( '업로드되는 모든 PNG·JPG 이미지에 적용됩니다. 이미 올라가 있는 이미지는 아래 WP-CLI 명령으로 일괄 변환할 수 있습니다.', 'kdr-image-optimize' ) . '</p>';
	}

	/**
	 * 최대 너비 필드.
	 *
	 * @return void
	 */
	public static function field_max_width() {
		$settings = KDR_IO_Converter::get_settings();
		?>
		<input type="number" min="100" max="5000" step="10"
			name="<?php echo esc_attr( KDR_IO_OPTION ); ?>[max_width]"
			value="<?php echo esc_attr( $settings['max_width'] ); ?>" class="small-text" />
		px
		<p class="description"><?php esc_html_e( '이 너비보다 넓은 이미지는 비율을 유지한 채 이 크기로 줄입니다. 기본 900px.', 'kdr-image-optimize' ); ?></p>
		<?php
	}

	/**
	 * 품질 필드.
	 *
	 * @return void
	 */
	public static function field_quality() {
		$settings = KDR_IO_Converter::get_settings();
		?>
		<input type="number" min="10" max="100" step="1"
			name="<?php echo esc_attr( KDR_IO_OPTION ); ?>[quality]"
			value="<?php echo esc_attr( $settings['quality'] ); ?>" class="small-text" />
		%
		<p class="description"><?php esc_html_e( '낮을수록 용량이 줄고 화질이 떨어집니다. 60~85 사이를 권장하며 기본 80입니다.', 'kdr-image-optimize' ); ?></p>
		<?php
	}

	/**
	 * WebP 변환 필드.
	 *
	 * @return void
	 */
	public static function field_convert_webp() {
		$settings = KDR_IO_Converter::get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( KDR_IO_OPTION ); ?>[convert_webp]" value="1"
				<?php checked( ! empty( $settings['convert_webp'] ) ); ?> />
			<?php esc_html_e( '업로드한 이미지를 WebP 로 변환합니다.', 'kdr-image-optimize' ); ?>
		</label>
		<p class="description"><?php esc_html_e( '끄면 원래 포맷(PNG/JPG) 그대로 리사이즈·압축만 합니다.', 'kdr-image-optimize' ); ?></p>
		<?php
	}

	/**
	 * 원본 삭제 필드.
	 *
	 * @return void
	 */
	public static function field_delete_original() {
		$settings = KDR_IO_Converter::get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( KDR_IO_OPTION ); ?>[delete_original]" value="1"
				<?php checked( ! empty( $settings['delete_original'] ) ); ?> />
			<?php esc_html_e( '변환이 끝나면 원본 파일을 삭제합니다.', 'kdr-image-optimize' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( '끄면 원본 파일을 삭제하지 않고 아래 경로로 옮겨 보관합니다.', 'kdr-image-optimize' ); ?><br />
			<code>wp-content/uploads/kdr-originals/년/월/파일명</code><br />
			<?php esc_html_e( '보관된 원본은 미디어 라이브러리에 나타나지 않고, 웹 주소로도 그대로 열립니다. FTP 나 파일 관리자로 직접 받아 되돌릴 수 있습니다.', 'kdr-image-optimize' ); ?><br />
			<strong><?php esc_html_e( '주의: 원본이 그대로 남으므로 서버 용량을 거의 2배로 사용합니다.', 'kdr-image-optimize' ); ?></strong>
		</p>
		<?php
	}

	/**
	 * 설정 화면 출력.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$env      = KDR_IO_Converter::environment();
		$settings = KDR_IO_Converter::get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( ! empty( $settings['convert_webp'] ) && empty( $env['webp'] ) ) : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( '서버 이미지 엔진이 WebP 를 지원하지 않습니다. WebP 변환을 끄거나 서버에 GD/Imagick WebP 지원을 추가하세요.', 'kdr-image-optimize' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $env['writable'] ) ) : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( '업로드 폴더에 쓸 수 없습니다. 이 상태로는 이미지 최적화가 동작하지 않습니다.', 'kdr-image-optimize' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $settings['delete_original'] ) ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( '원본 삭제가 꺼져 있습니다.', 'kdr-image-optimize' ); ?></strong>
						<?php esc_html_e( '최적화본과 원본이 함께 저장되므로 업로드 용량이 거의 2배가 됩니다. 아래 원본 백업 사용량을 확인하시고, 원본이 필요 없으면 삭제를 켜세요.', 'kdr-image-optimize' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( '서버 환경', 'kdr-image-optimize' ); ?></h2>
			<table class="widefat striped" style="max-width:820px">
				<tbody>
					<tr>
						<td style="width:220px"><strong><?php esc_html_e( '이미지 엔진', 'kdr-image-optimize' ); ?></strong></td>
						<td><?php echo $env['editor'] ? esc_html( $env['editor'] ) : esc_html__( '없음 (이미지 처리를 할 수 없습니다)', 'kdr-image-optimize' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WebP 저장 지원', 'kdr-image-optimize' ); ?></strong></td>
						<td><?php echo $env['webp'] ? esc_html__( '지원', 'kdr-image-optimize' ) : esc_html__( '미지원', 'kdr-image-optimize' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( '업로드 폴더', 'kdr-image-optimize' ); ?></strong></td>
						<td><code><?php echo esc_html( $env['upload_dir'] ); ?></code>
							<?php echo $env['writable'] ? esc_html__( '(쓰기 가능)', 'kdr-image-optimize' ) : esc_html__( '(쓰기 불가)', 'kdr-image-optimize' ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( '원본 백업 폴더', 'kdr-image-optimize' ); ?></strong></td>
						<td>
							<code><?php echo esc_html( $env['backup_dir'] ); ?></code>
							<?php if ( $env['backup_url'] ) : ?>
								<br /><span class="description"><?php echo esc_html( $env['backup_url'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! $env['backup_exists'] ) : ?>
								<br /><span class="description"><?php esc_html_e( '아직 없음 — 원본 삭제를 끄면 첫 업로드 때 자동으로 만들어집니다.', 'kdr-image-optimize' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( '원본 백업 사용량', 'kdr-image-optimize' ); ?></strong></td>
						<td>
							<?php if ( $env['backup_count'] > 0 ) : ?>
								<?php
								printf(
									/* translators: 1: number of files, 2: total size */
									esc_html__( '파일 %1$s개 · %2$s', 'kdr-image-optimize' ),
									esc_html( number_format_i18n( (int) $env['backup_count'] ) ),
									esc_html( size_format( (int) $env['backup_bytes'], 2 ) )
								);
								?>
							<?php else : ?>
								<?php echo esc_html( size_format( 0, 2 ) ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( '기존 이미지 일괄 변환', 'kdr-image-optimize' ); ?></h2>
			<p><?php esc_html_e( '이미 올라가 있는 이미지는 아래 명령으로 한 번에 변환할 수 있습니다. 먼저 --dry-run 으로 대상만 확인하세요.', 'kdr-image-optimize' ); ?></p>
			<p><code>wp kdr-image-optimize convert-all --dry-run</code></p>
			<p><code>wp kdr-image-optimize convert-all</code></p>
			<p class="description"><?php esc_html_e( 'WP-CLI 가 기본 PHP 를 사용해 mysqli/GD 를 못 찾는 서버라면, 웹에서 쓰는 PHP 로 실행해야 합니다.', 'kdr-image-optimize' ); ?></p>
		</div>
		<?php
	}
}
