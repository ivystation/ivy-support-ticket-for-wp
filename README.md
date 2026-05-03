# Ivy Support Ticket for WordPress

아이비넷의 1:1 지원 티켓 시스템(pm.ivynet.co.kr)을 워드프레스 어드민에서 직접 사용할 수 있도록 하는 플러그인입니다. 별도 자체 DB를 두지 않으며, 모든 티켓 데이터는 pm.ivynet.co.kr API를 통해 관리됩니다.

## 핵심 기능

- 워드프레스 사이드바 + Adminbar에 **Support Ticket** 메뉴 (서브메뉴: 새 티켓 작성 / 티켓 목록 / 설정)
- 워드프레스 사이트 = pm 측 조직(Organization) 1:1 매핑 — 같은 사이트에 등록된 사용자는 같은 조직에 귀속
- 설정 페이지에서 티켓 발행 가능한 사용자 명시 등록 (활성화 시 administrator/editor 자동 등록)
- 티켓 작성·열람·댓글·첨부파일(R2 presigned URL 직접 업로드) 양방향 동기화
- GitHub Releases 기반 자동 업데이트 (plugin-update-checker v5)

## 상태

**기획 단계** — 기획서는 [`docs/IST-플러그인 기획서.md`](docs/IST-플러그인%20기획서.md)에서 확인할 수 있습니다.

## 라이선스

GPL v2 or later (워드프레스 표준)
