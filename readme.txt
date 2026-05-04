=== Ivy Support Ticket for WordPress ===
Contributors: ivynet
Tags: support, ticket, helpdesk, headless, ivynet
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

워드프레스 어드민에서 직접 1:1 지원 티켓을 발행·관리하는 플러그인. 자체 DB 미사용 — pm.ivynet.co.kr API 연동.

== Description ==

이 플러그인은 ticket.ivynet.co.kr 고객 포털을 워드프레스 어드민에서 바로 사용할 수 있도록 합니다. SSO(Single Sign-On) 설정 시 메뉴 클릭 한 번으로 ticket.ivynet.co.kr에 자동 로그인됩니다. 모든 티켓 데이터는 pm.ivynet.co.kr REST API를 통해 관리되며, 워드프레스 사이트에 별도의 DB 테이블을 만들지 않습니다.

핵심 기능:

* 워드프레스 사이드바 + 상단 Adminbar에 "Support Ticket" 메뉴
* SSO 활성화 시: 메뉴 클릭 → ticket.ivynet.co.kr 새 탭 자동 로그인
* SSO 비활성화 시: WP 어드민 내장 UI로 티켓 직접 관리 (하위 호환)
* 설정 화면 4탭: API 연결 / SSO / 사용자 매핑 / 정보
* 한 사이트 = pm 시스템의 한 조직(Organization)으로 자동 매핑
* GitHub Releases 기반 원클릭 자동 업데이트

== Installation ==

1. 이 플러그인을 워드프레스에 업로드 후 활성화
2. 어드민 → Support Ticket → 설정 → API 연결 탭에서 API Base URL과 API Key 입력
3. "연결 테스트" 버튼으로 200 OK 확인
4. 사용자 매핑 탭에서 티켓을 발행할 사용자 확인 (administrator/editor는 자동 등록됨)

== Changelog ==

= 0.2.0 =
* feat(sso): ticket.ivynet.co.kr SSO 자동 로그인 기능 추가. 메뉴 클릭 시 HS256 JWT를 생성하여 ticket.ivynet.co.kr을 새 탭으로 열고 pm.ivynet.co.kr에서 세션을 발급받아 자동 로그인한다.
* feat(설정): SSO 탭 신설 — 티켓 포털 URL + SSO 시크릿 키 + 활성화 상태 표시.
* SSO 미설정 시 기존 내장 UI(티켓 목록·작성·상세) 그대로 동작 (하위 호환).

= 0.1.5 =
* feat(api): health() 요청에 siteUrl 파라미터 추가.

= 0.1.4 =
* breaking: 자동 등록(activate 시 administrator/editor 시드)·"기본값으로 재설정" 버튼·"신규 administrator/editor 자동 등록" 토글 모두 제거. 사용자는 설정 → 사용자 매핑 탭에서 검색·추가로만 등록한다.
* refactor: ajax_user_add / ajax_user_remove 응답에 갱신된 enrolled 배열을 포함시켜 클라이언트가 서버 상태 그대로 재렌더링하도록 변경(로컬 state 누락 가능성 제거).
* fix: 사용자 매핑 탭에 "설정 저장" 버튼 노출 안 됨(폼 submit 항목 없음).
* debug: settings.js의 사용자 매핑 바인딩과 추가 클릭 시 console.log 출력으로 진단 용이.

= 0.1.3 =
* fix(설정): 사용자 매핑 인터랙션이 별도 users.js로 분리되어 있던 구조에서 IVY_ST 글로벌 의존성 충돌이 발생하던 문제 해결.
* refactor: settings.js 단일 파일로 통합 — 연결 테스트 + 사용자 매핑(검색·추가·해지) 모두 한 핸들에서 처리. IVY_ST 미정의 시 콘솔 경고로 진단 가능.

= 0.1.2 =
* feat(설정): 사용자 매핑 탭 UI 개편 — 전체 사용자 목록 대신 등록된 사용자만 카드 형태로 표시.
* feat(설정): 이메일/이름/아이디로 검색해 사용자 추가, 행별 "해지" 버튼으로 즉시 해제. AJAX 기반.
* 기존 "관리자/편집자 전체로 초기화"·"신규 admin/editor 자동 등록" 옵션은 그대로 유지.

= 0.1.1 =
* fix: 설정 폼 탭 분리 처리 — "API 연결" 탭에서 저장 시 사용자 매핑(allowed_user_ids)이 빈 배열로 덮어써지던 문제 해결. 활성 탭의 필드만 patch에 포함하도록 수정.
* 우회 방법: 설정 → 사용자 매핑 탭 → "관리자/편집자 전체로 초기화" 버튼으로 즉시 복구 가능

= 0.1.0 =
* 플러그인 골격, 메뉴, 설정 페이지, ApiClient, 사용자 매핑 자동 등록 (Phase 2)
* 티켓 작성·목록·상세·댓글 UI (Phase 3)
* 첨부파일 업로드(R2 직접 PUT) / 다운로드(Presigned GET) — 새 티켓·댓글 양쪽 지원 (Phase 4)
* GitHub Releases 기반 자동 업데이트 (PUC v5.6) (Phase 5)
