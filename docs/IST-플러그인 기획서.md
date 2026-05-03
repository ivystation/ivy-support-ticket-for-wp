# IST(Ivy Support Ticket) for WordPress — 플러그인 기획서

**프로젝트명**: ivy-support-ticket-for-wp
**작성일**: 2026-05-03
**최종 승인 plan**: `/Users/stanley/.claude/plans/mossy-gliding-axolotl.md`
**저장소(예정)**: https://github.com/ivystation/ivy-support-ticket-for-wp (public)

---

## 1. 개요

ticket.ivynet.co.kr(Next.js 16 고객 포털)에서 제공하는 **1:1 지원 티켓 발행·열람·응답** 기능을 워드프레스 어드민 화면 내부에서 동일하게 사용할 수 있도록 하는 플러그인이다.

아이비넷이 운영·구축한 다수의 워드프레스 사이트(고객사 사이트)에 본 플러그인을 설치하면, 사이트 운영자(또는 사이트 관리자가 지정한 사용자)는 워드프레스 대시보드를 떠나지 않고 **pm.ivynet.co.kr**에 티켓을 등록하고, 응답을 받고, 첨부파일을 주고받을 수 있다.

### 1.1 이 플러그인이 해결하는 문제

| 현재 | 본 플러그인 도입 후 |
|---|---|
| 고객사 운영자가 ticket.ivynet.co.kr에 별도 계정을 만들고 로그인해야 함 | 워드프레스 어드민에서 클릭 한 번으로 티켓 발행 |
| 고객사 사이트마다 어떤 사람이 문의하는지 파악이 어려움 | 사이트 = 조직 1:1 매핑으로 자동 식별 |
| 워드프레스 콘텐츠 관련 문의에서 화면을 두 개 띄워야 함 | 동일 어드민 내에서 컨텍스트 유지 |

### 1.2 비범위(Out of Scope)

- 티켓 시스템 자체의 기능 확장(가격 견적, 프로젝트 승격 등)은 본 플러그인 범위가 아니다. 그러한 기능은 pm.ivynet.co.kr 어드민에서 STAFF가 처리한다.
- 일반 방문자가 워드프레스 사이트에서 티켓을 발행하는 프런트엔드 위젯은 본 1차 릴리스 범위가 아니다(추후 검토).

---

## 2. 결정 사항 요약 (사용자 승인)

| 항목 | 결정 |
|---|---|
| 백엔드 선행작업 | **Phase 1로 포함** — pm.ivynet.co.kr에 ApiKey 모델 + `/api/external/wp/*` 신설 |
| 사용자 매핑 | **명시적 등록 + 이메일 자동 매핑** — 활성화 시 administrator·editor를 기본값으로 자동 등록, 첫 진입 시 이메일로 pm User upsert |
| 티켓 본문 에디터 | **wp_editor (TinyMCE) HTML** — pm 측 BlockNoteViewer가 plain text fallback을 제공하므로 HTML도 안전 표시 |
| GitHub 저장소 | **ivystation/ivy-support-ticket-for-wp (public) + plugin-update-checker v5** — Actions로 tag push → zip → Release |
| Git 커밋 author | **`wpkorea` 사용자로만 커밋** — Claude 식별자(Co-Authored-By 트레일러, "Generated with Claude Code" 푸터 등) 일체 노출 금지 |

---

## 3. 사용자 시나리오

### 3.1 운영자(사이트 관리자)

1. 워드프레스 어드민 → 플러그인 → "Ivy Support Ticket for WordPress" 설치·활성화
2. **Support Ticket → 설정** 진입 → API Base URL(기본 `https://pm.ivynet.co.kr`)과 API Key 입력 → "연결 테스트" 버튼 클릭 → 200 OK 확인
3. **사용자 매핑** 탭 확인 — 활성화 시 administrator·editor 사용자가 자동 등록되어 있다. 필요 시 추가/제거.
4. 끝. 등록된 사용자가 즉시 티켓을 발행할 수 있다.

### 3.2 등록된 사용자(작성자)

1. 워드프레스 어드민 사이드바에 표시된 **Support Ticket → 새 티켓 작성** 진입
2. 제목, 카테고리, 우선순위, 본문(TinyMCE 에디터)을 입력하고 필요 시 첨부파일 업로드(최대 5개·10MB)
3. "제출" 클릭 → pm 시스템에 티켓 생성, `TK-2026-NNNN` 번호 부여, 목록 페이지로 리다이렉트
4. **티켓 목록**에서 본인이 발행한 티켓을 상태별로 확인. 상세 페이지에서 STAFF의 답글 확인 및 댓글로 회신.

### 3.3 STAFF (pm.ivynet.co.kr 어드민 사용자)

WP에서 발행된 티켓은 **기존 pm 어드민에서 동일하게 보인다.** 별도의 흐름이 없다. 티켓의 `source` 필드만 `PORTAL`이 아닌 새 enum 값(예: `WORDPRESS`) 또는 metadata로 구분 표시한다.

---

## 4. 기능 명세

### 4.1 WP 어드민 메뉴 / Adminbar

- **사이드바 최상위**: "Support Ticket" (대시아이콘 `dashicons-tickets-alt`)
- **상단 Adminbar 우측**: 동일한 진입 항목(아이콘 + 텍스트), 모바일에서도 노출
- **서브메뉴 3개**:
  1. 새 티켓 작성 — `admin.php?page=ivy-st-new`
  2. 티켓 목록 — `admin.php?page=ivy-st-list`
  3. 설정 — `admin.php?page=ivy-st-settings`
- **권한**:
  - 메뉴 자체는 `read` capability + `allowed_user_ids`에 포함된 user에게만 표시
  - 설정 페이지는 `manage_options` 보유자에게만 표시 (사이트 관리자 전용)

### 4.2 새 티켓 작성 페이지

| 필드 | 타입 | 검증 |
|---|---|---|
| 제목 | text | 2-200자, 필수 |
| 카테고리 | select | WORDPRESS / AI / DESIGN / MAINTENANCE / CONSULTING / OTHER, 필수 |
| 우선순위 | select | LOW / NORMAL / HIGH / URGENT, 기본 NORMAL |
| 본문 | wp_editor (TinyMCE) | HTML, `wp_kses_post`로 sanitize, 10자 이상 |
| 예산(선택) | text | metadata.budget |
| 완료 희망일(선택) | date | metadata.deadline (ISO) |
| 참고 URL(선택, 다중) | url | metadata.referenceUrls[] |
| 첨부파일 | file (multi) | 최대 5개, 각 10MB, 화이트리스트 MIME |

**제출 흐름**:
1. JS가 첨부파일을 하나씩 처리 — `admin-ajax.php?action=ivy_st_presign` → pm `/external/wp/uploads/presign` → presigned PUT URL → 브라우저에서 R2 직접 PUT → r2Key 회수
2. 모든 파일 업로드 완료 후 `admin-ajax.php?action=ivy_st_submit_ticket`으로 본문 + r2Keys 전송
3. 서버측에서 `wp_kses_post`로 본문 sanitize 후 ApiClient를 통해 pm `/external/wp/tickets`에 POST
4. 성공 응답의 ticketNumber로 목록 페이지에 리다이렉트(success notice 표시)

### 4.3 티켓 목록 페이지

- `WP_List_Table` 상속 클래스 `Ivy_ST_List_Table`
- 컬럼: 번호 / 제목(링크) / 카테고리 / 상태(배지) / 우선순위 / 작성일
- 상단 상태 탭: 전체 · 접수(SUBMITTED, IN_REVIEW) · 진행중(QUOTED, ACCEPTED, IN_PROGRESS) · 완료(COMPLETED, CLOSED)
- 페이지네이션(20개/페이지)
- API: `GET /api/external/wp/tickets?userEmail={current}&page=N&status=...`
- 60초 transient 캐시 키: `ivy_st_list_{userId}_{page}_{status}`

### 4.4 티켓 상세 페이지

- 헤더: 티켓번호 · 제목 · 상태 배지
- 메타 패널: 카테고리 · 우선순위 · 작성자 · 작성일 · metadata(budget/deadline/referenceUrls)
- 본문: HTML을 `wp_kses_post`로 정화 후 출력
- 첨부파일: 파일명 클릭 → `admin-ajax.php?action=ivy_st_presign_get` → 새 창에서 다운로드
- 댓글 영역:
  - 작성자(STAFF/CUSTOMER 시각적 구분: ticket 포털과 동일하게 STAFF는 primary-light 배경 + 초록 좌측 테두리)
  - 작성일시
  - 본문 HTML
  - 첨부파일 목록(있는 경우)
- 댓글 작성 폼: textarea + 첨부파일 다중 업로드. 제출 시 `POST /api/external/wp/tickets/{id}/comments`

### 4.5 설정 페이지 (탭 3개)

#### 탭 1: API 연결
- API Base URL (기본 `https://pm.ivynet.co.kr`, 변경 가능)
- API Key (입력 시 마스킹 표시, 저장 시 wp_options autoload=no)
- "연결 테스트" 버튼 → `GET /api/external/wp/health` → 응답 표시
- 어떤 조직(organization)에 바인딩되어 있는지 readonly 표시 (health 응답의 organizationId 기반)

#### 탭 2: 사용자 매핑
- **허용된 WP 사용자** 다중 선택 (Select2 또는 표준 multi-select)
- 사용자 행 별로 표시: 이메일, 이름, role, pm User ID(있으면), "재동기화" 버튼
- 부가 옵션:
  - "기본값으로 재설정" 버튼 → administrator + editor 역할의 모든 user로 목록 초기화
  - "신규 administrator/editor 자동 등록" 토글 (기본 ON) — `user_register`/`set_user_role` 훅으로 신규/승격된 admin·editor를 자동 추가
- "신규 administrator/editor 자동 등록" 옵션 동작:
  - 활성 시: `user_register` 또는 `set_user_role` 액션 발생 후 새 역할이 administrator·editor면 `allowed_user_ids`에 자동 추가(중복 제거)
  - 비활성 시: 어떤 자동 추가도 일어나지 않음 (수동 추가만 가능)

#### 탭 3: 정보
- 플러그인 버전 표시
- 디버그 로그 토글 (기본 OFF) — 활성 시 wp-content/uploads/ivy-support-ticket-debug.log 기록(API Key 등 마스킹)
- GitHub 저장소 링크
- 라이선스 정보 (GPL v2+)

---

## 5. 시스템 아키텍처

```
┌──────────────────────────────────┐    HTTPS + Bearer    ┌────────────────────────────┐
│  WordPress 사이트                │  ─────────────────▶  │  pm.ivynet.co.kr           │
│  (ivy-support-ticket-for-wp)     │                      │  /api/external/wp/*        │
│  ─ wp_options: api_base, api_key │                      │  ApiKey 인증 미들웨어       │
│  ─ user_meta: pm_user_id 캐시    │  ◀─────────────────  │  organizationId 자동 바인딩 │
│  ─ wp_editor HTML / 첨부 presign │     JSON / R2 URL    │  Prisma → User/Ticket/...  │
└──────────────────────────────────┘                      └────────────────────────────┘
                  │                                                   │
                  └──── 직접 PUT (presigned URL) ─────────────────────┴────▶  Cloudflare R2
```

**핵심 원칙**: WP 플러그인은 자체 DB 미사용. 모든 진실은 pm.ivynet.co.kr DB에 있다. WP는 wp_options + user_meta에 인증/매핑 정보만 캐싱한다.

### 5.1 인증 모델

| 주체 | 식별자 | 검증 방법 |
|---|---|---|
| WordPress 사이트 | API Key (Bearer 토큰) | pm 측 sha256 해시 비교 |
| 조직(Organization) | API Key에 바인딩된 organizationId | 발급 시점에 고정 |
| 작성자(WP user) | userEmail (요청 본문) | pm 측에서 organization 내 User upsert(/users/ensure) |

→ WP 사이트에 등록된 모든 허용 사용자는 자동으로 같은 organization에 귀속된다.

### 5.2 데이터 흐름 (티켓 작성)

```
WP user "John" (email: john@example.com) 클릭 "제출"
   │
   ▼
admin-ajax.php (nonce + capability 검증)
   │
   ▼
ApiClient::create_ticket
   ├─▶ POST /api/external/wp/users/ensure  { email: "john@example.com", name: "John" }
   │      ↳ pm: organization 내 User upsert → { userId: "u_xxx" }
   │
   └─▶ POST /api/external/wp/tickets       { userEmail, title, description, category, ... }
          ↳ pm: 기존 createTicketWithNumber 재사용 → { ticketNumber: "TK-2026-0042", id: "t_xxx" }
```

---

## 6. 단계별 구현 계획

### Phase 1 — pm.ivynet.co.kr 백엔드 선행 (3-4일)

1. Prisma 스키마에 `ApiKey` 모델 추가 → 마이그레이션 `20260503_add_api_key`
2. `src/lib/auth/api-key.ts` — Bearer 인증 미들웨어
3. 도메인 로직 분리: `src/lib/services/tickets.ts` 등 (portal/external 양쪽 재사용)
4. `/api/external/wp/*` 엔드포인트 7종 구현 (health / users.ensure / tickets CRUD / comments / uploads)
5. 어드민에 API Key 발급·관리 UI (`src/app/(admin)/api-keys/`)

**검증**: curl로 발급된 키를 사용하여 health → ensure → ticket 생성까지 end-to-end 동작 확인.

### Phase 2 — WP 플러그인 골격 + 설정 (2-3일)

- 플러그인 헤더, 활성화 훅, 메뉴, Adminbar
- Settings(API 연결, 사용자 매핑, 정보) 페이지
- ApiClient(`wp_remote_*` 래퍼), 연결 테스트 버튼
- 활성화 시 administrator + editor 자동 등록 로직

**검증**: WP 사이트에 zip 활성화 → 설정 → 연결 테스트 200.

### Phase 3 — 티켓 CRUD UI (4-5일)

- 새 티켓 작성, 티켓 목록, 티켓 상세 페이지
- 사용자 매핑(첫 진입 시 ensure → user_meta 캐시)
- 권한 체크(`allowed_user_ids` 검증)

**검증**: WP 어드민에서 작성한 티켓이 ticket.ivynet.co.kr 어드민에 표시. 다른 사용자 티켓은 비공개.

### Phase 4 — 댓글 + 첨부파일 (3-4일)

- 첨부 업로드(presign → R2 직접 PUT)
- 첨부 다운로드(presign-get)
- 댓글 양방향 동기화

**검증**: 5MB PDF 업로드/다운로드, 댓글 양방향 동작.

### Phase 5 — GitHub 자동 업데이트 + 다국어 (1-2일)

- plugin-update-checker v5 임베드
- GitHub Actions 릴리스 워크플로 (tag → zip → Release)
- ko_KR / en_US 다국어

**검증**: `v0.1.0` 태그 push → WP 어드민 업데이트 배지 → 원클릭 갱신.

총 예상 일정: **13-18일** (단독 풀타임 기준)

---

## 7. 기술 스택 / 외부 의존성

| 영역 | 선택 |
|---|---|
| WordPress 최소 버전 | 6.0+ |
| PHP 최소 버전 | 7.4+ (8.x 권장) |
| 자동 업데이트 | YahnisElsts/plugin-update-checker v5 (vendor/ 임베드) |
| HTTP 통신 | wp_remote_get / wp_remote_post |
| 에디터 | `wp_editor()` (WordPress 표준 TinyMCE) |
| 목록 UI | `WP_List_Table` (WordPress 표준) |
| 사용자 다중 선택 | Select2 (이미 WP에 포함된 버전) 또는 표준 `<select multiple>` |
| 외부 API | pm.ivynet.co.kr `/api/external/wp/*` |
| 파일 저장소 | Cloudflare R2 (presigned URL) |

Composer는 사용하지 않는다(단일 vendor/PUC만 임베드). 일반 사용자가 zip 업로드 한 번으로 동작해야 하므로.

---

## 8. 보안 고려사항

- **API Key 저장**: pm DB는 sha256 hash만 저장. WP는 wp_options에 평문(`autoload=no`) 저장. 노출 시 어드민 UI에서 즉시 회수 가능.
- **AJAX 핸들러**: 모든 admin-ajax 핸들러는 `check_ajax_referer` + capability 체크. 비-admin이 직접 호출 차단.
- **본문 sanitize**: 티켓·댓글 본문은 입력·출력 양쪽에서 `wp_kses_post`. 표시 시 추가 검증.
- **R2 presign 만료**: 15분(기존 portal 정책 유지). r2Key 네임스페이스 `tickets/uploads/wp/{organizationId}/{uuid}`로 조직 간 격리.
- **사용자 매핑**: 게스트 admin이 추가될 위험을 차단하기 위해 매핑은 명시적 user ID 등록 방식. "신규 admin/editor 자동 등록" 옵션은 사이트 운영자가 끌 수 있다.
- **디버그 로그**: 기본 OFF. 활성 시에도 API Key 등 민감 데이터는 마스킹 처리.

---

## 9. Git 커밋 정책

| 저장소 | author | Co-Authored-By |
|---|---|---|
| `ivy-support-ticket-for-wp` (본 플러그인) | `wpkorea` 고정 | **포함 금지** — Claude 식별자, "Generated with Claude Code" 푸터 모두 제외 |
| `pm.ivynet.co.kr` (Phase 1 백엔드 추가) | 기존 정책 유지 | 기존 정책 유지 |

플러그인 저장소 작업 시 매 커밋 직전 `git -c user.name="wpkorea" -c user.email="<wpkorea-email>" commit ...` 형태로 author를 명시한다. 정확한 wpkorea 이메일은 Phase 2 시작 시점에 사용자에게 1회 확인한다.

커밋 메시지는 한국어 conventional commit (`feat:`, `fix:`, `docs:`, `chore:` 등)로 작성한다.

---

## 10. End-to-End 검증 시나리오 (전 단계 완료 시점)

1. pm 어드민에서 ApiKey 발급(조직 "테스트") → 평문 1회 복사
2. 워드프레스 테스트 사이트에 zip 활성화 → 설정에 API Base URL + Key 입력 → 연결 테스트 200
3. 설정 → 사용자 매핑에서 administrator + editor 자동 등록 확인 (별도 추가 불필요)
4. 새 티켓 작성: 제목 / 카테고리 / 본문(이미지 1개) → 제출 → 목록에 `TK-2026-NNNN` 표시
5. ticket.ivynet.co.kr 어드민(STAFF)에서 답글 작성 + 파일 첨부
6. WP 어드민으로 돌아와 새로고침 → 댓글·첨부 동기화 확인 → 첨부 다운로드 성공
7. `v0.1.1` 태그 push → GitHub Release 생성 → WP 어드민 업데이트 배지 → 원클릭 갱신

---

## 11. 향후 검토 항목 (1차 릴리스 이후)

- 프런트엔드 위젯(비로그인 방문자가 사이트 프런트에서 티켓 발행)
- WordPress 멀티사이트(network) 지원 — 현재는 단일 사이트 전제
- 다른 SaaS 워드프레스 플랫폼(WP Engine 등) 호환성 검증
- 알림 통합 — pm 측 답글 발생 시 WP 어드민 notice + 이메일

---

## 12. 변경 이력

| 일자 | 버전 | 변경 사항 |
|---|---|---|
| 2026-05-03 | 0.0.1 | 초안 작성. plan 승인 후 docs/ 임포트. |
