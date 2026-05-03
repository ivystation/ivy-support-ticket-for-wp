=== Ivy Support Ticket for WordPress ===
Contributors: ivynet
Tags: support, ticket, helpdesk, headless, ivynet
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

워드프레스 어드민에서 직접 1:1 지원 티켓을 발행·관리하는 플러그인. 자체 DB 미사용 — pm.ivynet.co.kr API 연동.

== Description ==

이 플러그인은 ticket.ivynet.co.kr 고객 포털의 핵심 기능(티켓 발행·열람·댓글·첨부)을 워드프레스 어드민 화면 내부에서 사용할 수 있도록 합니다. 모든 데이터는 pm.ivynet.co.kr REST API를 통해 관리되며, 워드프레스 사이트에 별도의 DB 테이블을 만들지 않습니다.

핵심 기능:

* 워드프레스 사이드바 + 상단 Adminbar에 "Support Ticket" 메뉴
* 서브메뉴: 새 티켓 작성 / 티켓 목록 / 설정
* 한 사이트 = pm 시스템의 한 조직(Organization)으로 자동 매핑
* 활성화 시 administrator·editor 역할의 사용자가 자동으로 허용 사용자에 등록 (관리자가 추가/제거 가능)
* GitHub Releases 기반 원클릭 자동 업데이트 (Phase 5에서 활성화)

== Installation ==

1. 이 플러그인을 워드프레스에 업로드 후 활성화
2. 어드민 → Support Ticket → 설정 → API 연결 탭에서 API Base URL과 API Key 입력
3. "연결 테스트" 버튼으로 200 OK 확인
4. 사용자 매핑 탭에서 티켓을 발행할 사용자 확인 (administrator/editor는 자동 등록됨)

== Changelog ==

= 0.1.0 =
* 첫 골격 — 메뉴, 설정 페이지, ApiClient, 사용자 매핑 자동 등록.
* 티켓 CRUD UI는 다음 버전에서 제공.
