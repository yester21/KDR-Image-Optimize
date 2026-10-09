# KDR 이미지 최적화 (KDR Image Optimize)

업로드한 이미지를 지정한 최대 너비로 리사이즈하고 지정한 품질로 다시 저장하는 워드프레스 플러그인입니다.
WebP 변환 여부와 원본 삭제 여부를 각각 선택할 수 있습니다.

- 제작: **김대리닷넷** — <https://kimderi.net>
- 라이선스: GPL-2.0-or-later

## 주요 기능

| 설정 | 기본값 | 설명 |
|---|---|---|
| 최대 이미지 너비 | `900` px | 이보다 넓은 이미지는 비율을 유지한 채 줄입니다. (100~5000) |
| 압축률(품질) | `80` % | 낮을수록 용량이 줄고 화질이 떨어집니다. (10~100) |
| WebP 변환 | 켜짐 | PNG/JPG 를 WebP 로 변환해 서빙합니다. |
| 원본 삭제 | 켜짐 | 변환이 끝난 뒤 원본 파일을 삭제합니다. |

기본값 조합이 기존 동작(원본 삭제 + WebP 변환)과 같습니다.

## 동작 방식

```
업로드
  └ wp_generate_attachment_metadata (priority 999)
      ├ 대상 확인        PNG / JPG / JPEG, 20KB 이상
      ├ 리사이즈         너비 > 최대너비 이면 비율 유지 축소
      ├ 품질 적용        압축률 설정값
      ├ 저장
      │   ├ WebP 변환 ON  → 이름.webp 새로 생성
      │   └ WebP 변환 OFF → 이름.kdr-tmp.<확장자> 에 쓴 뒤 원본과 교체
      ├ 원본 처리
      │   ├ 원본 삭제 ON  → 원본 삭제 (WebP OFF 면 교체로 대체)
      │   └ 원본 삭제 OFF → WebP ON: 원본을 그 자리에 유지
      │                     WebP OFF: uploads/kdr-originals/ 로 이동 보관
      ├ _wp_attached_file 갱신
      ├ post_mime_type 을 실제 포맷과 일치시킴
      └ 구 포맷 썸네일(-가로x세로) · -scaled 정리
```

## 안전장치

- **20KB 미만 파일은 건드리지 않습니다.** 아이콘·로고가 뭉개지는 것을 막습니다. `kdr_io_min_bytes` 필터로 조절할 수 있습니다.
- **결과가 원본보다 크면 원본을 유지합니다.** 압축이 손해인 경우를 걸러냅니다.
- **썸네일 정리는 `이름-가로x세로.확장자` 규격만** 지웁니다.
  접두사만 비교해 지우면 `undercover-silo.png` 를 변환할 때 `undercover-silo-cat.png` 처럼 접두사가 같은 **다른 첨부파일의 원본까지 삭제**됩니다. 이 플러그인은 그 문제를 피합니다.
- **첨부의 MIME 타입을 실제 파일과 일치**시킵니다. 미디어 라이브러리·REST·다른 플러그인이 어긋나지 않습니다.
- **같은 포맷 압축 시 임시 파일에 먼저 저장**한 뒤 교체합니다. 저장이 실패해도 원본이 손상되지 않습니다.

## 요구사항

- WordPress 5.8 이상
- PHP 7.4 이상
- WebP 변환을 쓰려면 GD 또는 Imagick 의 WebP 저장 지원 필요
  - 지원하지 않으면 설정 화면에 경고가 뜨고, 자동으로 변환하지 않고 원본을 유지합니다.

## 설치

1. 이 저장소를 `wp-content/plugins/kdr-image-optimize` 로 복사합니다.

   ```bash
   git clone https://github.com/<owner>/<repo>.git wp-content/plugins/kdr-image-optimize
   ```

2. 플러그인 목록에서 **KDR 이미지 최적화** 를 활성화합니다.
3. **설정 → KDR 이미지 최적화** 에서 값을 조정합니다.

## WP-CLI

기존에 올라가 있는 이미지를 일괄 변환합니다.

```bash
# 대상만 확인
wp kdr-image-optimize convert-all --dry-run

# 실제 변환
wp kdr-image-optimize convert-all

# 개수 제한
wp kdr-image-optimize convert-all --limit=100
```

실제 파일은 `.webp` 인데 첨부의 MIME 타입이 옛 포맷으로 남아 있다면:

```bash
wp kdr-image-optimize fix-mime --dry-run
wp kdr-image-optimize fix-mime
```

### WP-CLI 가 기본 PHP 때문에 실패할 때

`wp` 가 `/usr/bin/php` 처럼 `mysqli`·`GD`·`Imagick` 이 없는 PHP 를 사용하면 아래 오류가 납니다.

```
Error: Your PHP installation appears to be missing the MySQL extension ...
```

이때는 웹에서 쓰는 PHP 로 실행하세요.

```bash
/usr/local/lsws/lsphp83/bin/php /usr/bin/wp kdr-image-optimize convert-all --path=/home/example/public_html
```

## 파일 구조

```
kdr-image-optimize/
├── kdr-image-optimize.php              플러그인 헤더 · 부트스트랩
├── includes/
│   ├── class-kdr-io-converter.php      변환 엔진
│   ├── class-kdr-io-settings.php       관리자 설정 화면
│   └── class-kdr-io-cli.php            WP-CLI 명령
├── uninstall.php                       삭제 시 정리
├── readme.txt                          워드프레스 배포용
└── README.md
```

## 후크

| 후크 | 종류 | 설명 |
|---|---|---|
| `kdr_io_min_bytes` | filter | 처리할 최소 파일 크기(바이트). 기본 `20480` |

```php
// 10KB 미만도 처리하도록 변경
add_filter( 'kdr_io_min_bytes', function () {
    return 10240;
} );
```

## 메타 키

| 키 | 설명 |
|---|---|
| `_kdr_io_original_file` | 원본 삭제를 끄고 같은 포맷으로 압축했을 때, 보관된 원본의 상대 경로 |

## 제거

플러그인을 삭제하면 설정과 `_kdr_io_original_file` 메타가 지워집니다.
`uploads/kdr-originals/` 에 보관된 원본 이미지는 사용자 자산이므로 **지우지 않습니다.**

## 알려진 한계

- 업로드 시점에만 자동 처리합니다. 기존 이미지는 WP-CLI 로 일괄 변환해야 합니다.
- WebP 로 변환하고 원본을 삭제하면 WebP 미지원 환경을 위한 폴백이 없습니다.
- GIF(움직이는 이미지)와 AVIF 는 처리하지 않습니다.
- 사이트가 `srcset` 을 출력하지 않으면 반응형 분기 효과는 없습니다.

## 변경 이력

### 1.0.0
- 최초 배포
- 최대 너비 · 압축 품질 · WebP 변환 · 원본 삭제 설정
- 설정 화면 및 서버 환경 점검 표시
- WP-CLI `convert-all`, `fix-mime`
- 썸네일 정리 시 접두사가 같은 다른 첨부파일을 삭제하던 문제 수정
- 첨부 MIME 타입이 실제 파일과 어긋나던 문제 수정
- 일괄 변환에서 JPEG 가 누락되던 문제 수정
