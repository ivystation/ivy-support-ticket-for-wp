# Ivy Support Ticket for WordPress

아이비넷의 1:1 지원 티켓 시스템(pm.ivynet.co.kr)을 워드프레스 어드민에서 직접 사용할 수 있도록 하는 플러그인. 자체 DB를 사용하지 않으며, 모든 티켓 데이터는 pm.ivynet.co.kr REST API를 통해 관리됩니다.

## 핵심 기능

- 워드프레스 사이드바 + Adminbar에 **Support Ticket** 메뉴 (서브메뉴: 새 티켓 작성 / 티켓 목록 / 설정)
- 사이트 = pm 측 조직(Organization) 1:1 매핑 — 같은 사이트의 등록된 사용자는 같은 조직에 귀속
- 활성화 시 administrator·editor가 자동으로 허용 사용자 목록에 등록 (관리자가 추가/제거 가능)
- TinyMCE 본문 + 카테고리/우선순위 + 메타(예산·마감·참고URL) 입력
- 첨부파일 업로드(브라우저 → Cloudflare R2 직접 PUT) / 다운로드(presigned GET URL → 새 창)
- STAFF/CUSTOMER 댓글 시각 구분 (좌측 보더 + 색상)
- GitHub Releases 기반 자동 업데이트 (plugin-update-checker v5)

## 설치 및 설정

### 1. 설치
- WP 어드민 → 플러그인 → 새 플러그인 추가 → 플러그인 업로드 → `ivy-support-ticket-for-wp.zip` 활성화

### 2. API 키 발급 및 등록
- pm.ivynet.co.kr 어드민 → API 키 → 발급 (조직 선택, 평문 1회 노출)
- WP 어드민 → Support Ticket → 설정 → API 연결 탭에 Base URL과 키 입력
- "연결 테스트" 버튼으로 200 OK 확인

### 3. R2 CORS 등록 (첨부파일 사용 시 필수)
브라우저가 Cloudflare R2로 직접 PUT 업로드를 수행하므로, R2 버킷의 CORS 정책에 사이트 origin을 등록해야 합니다. 설정 → 정보 탭에 권장 JSON 규칙이 사이트 origin과 함께 표시됩니다.

### 4. 사용자 매핑
설정 → 사용자 매핑 탭에서 티켓을 발행할 수 있는 워드프레스 사용자를 선택하세요. 활성화 시 administrator·editor가 자동 등록되어 있으며, 신규 administrator/editor도 옵션 활성 시 자동으로 추가됩니다.

## 자동 업데이트

플러그인은 plugin-update-checker v5를 통해 GitHub Releases를 모니터링합니다. 새 버전 release가 게시되면 WP 어드민의 플러그인 페이지에 업데이트 배지가 표시됩니다.

릴리스 절차 (메인테이너):

```bash
# 헤더 Version, IVY_ST_VERSION 상수, readme.txt Stable tag을 동일 버전으로 갱신
git tag v0.1.0
git push origin v0.1.0
```

GitHub Actions 워크플로 [`release.yml`](.github/workflows/release.yml)이 자동으로:
1. 세 곳의 버전 일관성 검증
2. `ivy-support-ticket-for-wp.zip` 빌드 (vendor/ 포함, 개발 산출물 제외)
3. GitHub Release 생성 + zip을 자산으로 첨부

## 파일 구조

```
ivy-support-ticket-for-wp/
├── ivy-support-ticket.php          # 헤더, bootstrap, 상수
├── uninstall.php                   # 제거 시 옵션·user_meta 정리
├── readme.txt                      # WordPress 표준 readme
├── README.md, LICENSE (GPL v2)
├── includes/
│   ├── class-plugin.php            # boot/activate/deactivate
│   ├── class-settings.php          # 옵션 + 활성화 시드
│   ├── class-labels.php            # enum 한국어 라벨
│   ├── class-api-client.php        # wp_remote_* 래퍼
│   ├── class-user-mapping.php      # WP user ↔ pm User 매핑
│   └── class-updater.php           # PUC v5 어댑터
├── admin/
│   ├── class-admin-pages.php       # 메뉴 + Adminbar + AJAX
│   ├── class-ticket-list-table.php # WP_List_Table 상속
│   └── views/
│       ├── settings.php
│       ├── ticket-list.php
│       ├── ticket-new.php
│       └── ticket-show.php
├── assets/
│   ├── css/admin.css
│   └── js/{settings,ticket-new,ticket-show,attach-uploader}.js
├── languages/
│   └── ivy-support-ticket.pot
├── vendor/
│   └── plugin-update-checker/      # PUC v5.6 임베드
└── .github/workflows/release.yml   # tag → zip → Release
```

## 라이선스

GPL v2 or later (워드프레스 플러그인 표준)

## 저장소

https://github.com/ivystation/ivy-support-ticket-for-wp
