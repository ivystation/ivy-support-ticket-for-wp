# Ivy Support Ticket for WordPress

아이비넷의 1:1 지원 티켓 시스템(`ticket.ivynet.co.kr`)을 워드프레스 어드민에서 직접 사용할 수 있도록 하는 플러그인. 자체 DB를 사용하지 않으며, 모든 티켓 데이터는 pm.ivynet.co.kr REST API를 통해 관리됩니다.

## 작동 방식

SSO(Single Sign-On)가 설정된 경우, WP 어드민의 메뉴를 클릭하면 현재 로그인한 워드프레스 계정으로 `ticket.ivynet.co.kr`에 자동 로그인되어 새 탭으로 열립니다.

```
[WP 메뉴 클릭]
  → 플러그인이 HS256 JWT 토큰 생성 (5분 만료)
  → 새 탭에서 ticket.ivynet.co.kr/api/auth/sso?token=... 열림
  → pm.ivynet.co.kr에서 토큰 검증 → 세션 쿠키 발급
  → 사용자가 자동 로그인된 상태로 포털 접속
```

SSO 미설정 시에는 기존 WP 어드민 내장 UI(티켓 목록·작성·상세)가 그대로 동작합니다.

## 핵심 기능

- 워드프레스 사이드바 + Adminbar에 **Support Ticket** 메뉴
- SSO 활성화 시 — ticket.ivynet.co.kr을 새 탭으로 열고 자동 로그인
- SSO 비활성화 시 — WP 어드민 내장 UI로 티켓 작성·목록·상세 직접 렌더링
- 사이트 = pm 측 조직(Organization) 1:1 매핑
- 설정 화면 4탭: API 연결 / SSO / 사용자 매핑 / 정보
- GitHub Releases 기반 자동 업데이트 (plugin-update-checker v5)

## 설치 및 설정

### 1. 설치
WP 어드민 → 플러그인 → 새 플러그인 추가 → 플러그인 업로드 → `ivy-support-ticket-for-wp.zip` 활성화

### 2. API 키 등록
- pm.ivynet.co.kr 어드민 → API 키 → 발급 (조직 선택, 평문 1회 노출)
- WP 어드민 → Support Ticket → 설정 → **API 연결** 탭에 Base URL과 키 입력
- "연결 테스트" 버튼으로 200 OK 확인

### 3. SSO 설정 (권장)
- pm 서버 Coolify 환경변수에 `SSO_SECRET=<32자 이상 랜덤 문자열>` 추가 후 재배포
- WP 어드민 → Support Ticket → 설정 → **SSO** 탭
  - 티켓 포털 URL: `https://ticket.ivynet.co.kr` (기본값)
  - SSO 시크릿 키: pm 서버의 `SSO_SECRET`과 동일한 값 입력
- 저장 후 상태 표시가 "활성화됨"으로 변경되면 완료

### 4. 사용자 매핑
설정 → **사용자 매핑** 탭에서 티켓을 발행할 수 있는 워드프레스 사용자를 검색해 명시적으로 등록하세요.

### 5. R2 CORS 등록 (내장 UI 사용 시 필수)
SSO 미설정으로 내장 첨부파일 업로드를 쓰는 경우, R2 버킷 CORS 정책에 사이트 origin을 등록해야 합니다. 설정 → **정보** 탭에 권장 JSON 규칙이 사이트 origin과 함께 표시됩니다.

## 자동 업데이트

플러그인은 plugin-update-checker v5를 통해 GitHub Releases를 모니터링합니다.

릴리스 절차 (메인테이너):

```bash
# ivy-support-ticket.php 헤더 Version + IVY_ST_VERSION 상수 + readme.txt Stable tag 동일하게 수정
git tag v0.2.0
git push origin v0.2.0
```

GitHub Actions [`release.yml`](.github/workflows/release.yml)이 자동으로:
1. 세 곳의 버전 일관성 검증
2. `ivy-support-ticket-for-wp.zip` 빌드 (vendor/ 포함, 개발 산출물 제외)
3. GitHub Release 생성 + zip을 자산으로 첨부

## 파일 구조

```
ivy-support-ticket-for-wp/
├── ivy-support-ticket.php          # 헤더, 상수, bootstrap
├── uninstall.php                   # 제거 시 옵션·user_meta 정리
├── readme.txt                      # WordPress 표준 readme
├── includes/
│   ├── class-plugin.php            # boot / activate / deactivate
│   ├── class-settings.php          # wp_options 게이트웨이
│   │                               # 필드: api_base, api_key, allowed_user_ids,
│   │                               #       debug, ticket_url, sso_secret
│   ├── class-sso.php               # HS256 JWT 생성 + ticket SSO URL 조립
│   ├── class-labels.php            # enum 한국어 라벨
│   ├── class-api-client.php        # wp_remote_* 래퍼 (pm external API)
│   ├── class-user-mapping.php      # WP user ↔ pm User 매핑·캐싱
│   └── class-updater.php           # plugin-update-checker v5.6 어댑터
├── admin/
│   ├── class-admin-pages.php       # 메뉴 + Adminbar + AJAX + SSO 런처
│   ├── class-ticket-list-table.php # WP_List_Table 상속 (내장 UI용)
│   └── views/
│       ├── settings.php            # 탭 4개: API 연결 / SSO / 사용자 매핑 / 정보
│       ├── ticket-list.php         # 내장 UI: 티켓 목록
│       ├── ticket-new.php          # 내장 UI: 새 티켓 작성
│       └── ticket-show.php         # 내장 UI: 티켓 상세
├── assets/
│   ├── css/admin.css
│   └── js/{settings,ticket-new,ticket-show,attach-uploader}.js
├── languages/
│   └── ivy-support-ticket.pot
├── vendor/
│   └── plugin-update-checker/      # PUC v5.6 임베드
└── .github/workflows/release.yml   # tag → zip → Release
```

## 아키텍처 (SSO 활성화 시)

```
WordPress 어드민                 ticket.ivynet.co.kr         pm.ivynet.co.kr
      │                                │                           │
      │  window.open(SSO URL)          │                           │
      │──────────────────────────────▶│                           │
      │                                │ POST /api/portal/auth/sso │
      │                                │──────────────────────────▶│
      │                                │                           │ JWT 검증
      │                                │  Set-Cookie: ivy_pm_session│ 세션 발급
      │                                │◀──────────────────────────│
      │                                │ 쿠키 전달 + /portal 리디렉션
      │                                │
      │                  사용자: 자동 로그인 상태로 포털 접속
```

## 라이선스

GPL v2 or later

## 저장소

https://github.com/ivystation/ivy-support-ticket-for-wp
