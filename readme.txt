=== KDR 이미지 최적화 ===
Contributors: kimderi
Tags: image, webp, optimize, compress, resize, thumbnail
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

업로드한 이미지를 지정한 최대 너비로 줄이고 지정한 품질로 다시 저장합니다. WebP 변환과 원본 삭제 여부를 각각 선택할 수 있습니다.

== Description ==

이미지를 업로드하는 순간 자동으로 처리합니다.

* **리사이즈** — 지정한 최대 너비(기본 900px)보다 넓은 이미지는 비율을 유지한 채 줄입니다.
* **압축** — 지정한 품질(기본 80)로 다시 저장합니다.
* **WebP 변환** — 켜면 PNG/JPG 를 WebP 로 변환해 서빙합니다. (기본 켜짐)
* **원본 삭제** — 켜면 변환이 끝난 뒤 원본 파일을 삭제합니다. (기본 켜짐)

기본값은 "원본을 삭제하고 WebP 로 변환해 업로드"이며, 기존에 쓰던 동작과 같습니다.

= 안전장치 =

* 20KB 미만 파일(아이콘·로고)은 건드리지 않습니다. `kdr_io_min_bytes` 필터로 조절할 수 있습니다.
* 변환 결과가 원본보다 크거나 같으면 원본을 그대로 둡니다.
* 원본 삭제를 끄면 원본을 보관합니다. WebP 변환을 켠 경우 원본이 그 자리에 남아 예전 `.png`/`.jpg` 주소도 계속 열립니다.
* 같은 포맷으로 압축할 때(WebP 변환 꺼짐)는 `uploads/kdr-originals/` 에 원본을 옮겨 보관한 뒤 교체합니다.
* 첨부의 MIME 타입을 실제 파일과 일치시켜, 미디어 라이브러리와 다른 플러그인이 어긋나지 않습니다.
* 썸네일 정리는 `이름-가로x세로.확장자` 규격만 지웁니다. 접두사가 같은 다른 이미지의 원본을 지우지 않습니다.

= 서버 요구사항 =

WebP 변환을 쓰려면 GD 또는 Imagick 에 WebP 저장 지원이 있어야 합니다. 지원하지 않으면 설정 화면에서 경고를 보여주며, 자동으로 변환하지 않고 원본을 유지합니다.

= 기존 이미지 일괄 변환 =

    wp kdr-image-optimize convert-all --dry-run
    wp kdr-image-optimize convert-all

실제 파일은 `.webp` 인데 MIME 타입이 옛 포맷으로 남아 있다면:

    wp kdr-image-optimize fix-mime

WP-CLI 가 기본 PHP 를 사용해 `mysqli`/`GD` 를 찾지 못하는 서버에서는 웹에서 쓰는 PHP 로 실행하세요.

    /usr/local/lsws/lsphp83/bin/php /usr/bin/wp kdr-image-optimize convert-all --path=/path/to/wordpress

== Installation ==

1. `kdr-image-optimize` 폴더를 `/wp-content/plugins/` 에 올립니다.
2. 플러그인 목록에서 **KDR 이미지 최적화** 를 활성화합니다.
3. **설정 → KDR 이미지 최적화** 에서 값을 조정합니다.

== Frequently Asked Questions ==

= 원본을 지우면 되돌릴 수 없나요? =

네. 원본 삭제를 켜면 원본 파일이 사라집니다. 되돌릴 가능성을 남기려면 원본 삭제를 끄세요.

= WebP 를 지원하지 않는 브라우저는 어떻게 되나요? =

변환한 파일은 `.webp` 주소로 서빙됩니다. 원본 삭제를 켠 상태에서는 대체 파일이 없습니다. 폴백이 필요하면 원본 삭제를 끄세요.

= 이미 올라간 이미지도 처리되나요? =

업로드 시점에만 자동 처리됩니다. 기존 이미지는 `wp kdr-image-optimize convert-all` 로 일괄 변환하세요.

= GIF 는 처리되나요? =

아니요. PNG/JPG/JPEG 만 처리합니다. 움직이는 GIF 가 깨지는 것을 막기 위해서입니다.

== Changelog ==

= 1.0.0 =
* 최초 배포.
* 최대 너비, 압축 품질, WebP 변환, 원본 삭제 설정 추가.
* 설정 화면 및 서버 환경 점검 표시.
* WP-CLI `convert-all`, `fix-mime` 명령 추가.
* 썸네일 정리 시 접두사가 같은 다른 첨부파일을 삭제하던 문제 수정.
* 첨부의 MIME 타입이 실제 파일과 어긋나던 문제 수정.
* 일괄 변환에서 JPEG 가 누락되던 문제 수정.

== Upgrade Notice ==

= 1.0.0 =
최초 배포.
