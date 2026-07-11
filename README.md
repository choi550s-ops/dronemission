# 통합무인전투 통제체계 (IUCCS)

PHP + MariaDB 웹 애플리케이션 + **GitHub Actions 자동 FTP 배포**.
`main`에 push하면 파일이 웹호스팅 서버로 FTP 업로드되고, `migrate.php` 호출 시 MariaDB에 테이블이 자동 생성됩니다.

```
   내 PC ──git push(main)──► GitHub ──(Actions: FTP)──► 웹호스팅 public_html
                                                          │
                              브라우저로 migrate.php?token=… 한 번 호출 ──► MariaDB (테이블 자동 생성)
```

---

## 1. 구성

| 경로 | 내용 |
|---|---|
| `index.php` | 프런트 컨트롤러 + JSON API (`/api/*`) |
| `migrate.php` | **웹 마이그레이션(토큰 보호)** — FTP 호스트용 |
| `bin/migrate.php` | CLI 마이그레이션 (SSH 호스트용, 선택) |
| `src/` | Database · Auth · helpers · Migrator |
| `config/config.php` | `.env` 로더 |
| `db/migrations/*.sql` | 스키마·시드 (순번대로 적용) |
| `public/dashboard/` | 관제 대시보드 화면 |
| `public/operator/` | 조종자(운용자) 앱 화면 |
| `.github/workflows/deploy.yml` | 자동 FTP 배포 워크플로 |
| `.htaccess` | 라우팅 + 민감파일 접근 차단 |

---

## 2. 준비 (최초 1회)

### (1) 호스팅 패널에서 MariaDB DB 생성
카페24·가비아 등에서 DB를 만들고 값을 메모: DB 호스트(대개 `localhost`), 이름, 사용자, 비밀번호.
(테이블은 시스템이 자동 생성합니다.)

### (2) GitHub 저장소 Secrets 등록
저장소 → **Settings → Secrets and variables → Actions**

| Secret | 예시 | 설명 |
|---|---|---|
| `FTP_SERVER` | `ftp.mysite.co.kr` 또는 IP | FTP 주소 |
| `FTP_USERNAME` | 호스팅 계정 | |
| `FTP_PASSWORD` | ******** | |
| `FTP_PORT` | `21` | (선택, 기본 21) |
| `FTP_SERVER_DIR` | `/www/` (카페24) · `/html/`(가비아) | **끝에 `/` 필수.** 웹 루트 경로 |
| `SITE_URL` | `https://mysite.co.kr` | (선택) 배포 후 자동 마이그레이션용 |
| `MIGRATE_TOKEN` | 긴 무작위 문자열 | (선택) 위와 함께 자동 마이그레이션용 |

> **FTP_SERVER_DIR 값 찾기** — FileZilla로 같은 계정에 접속했을 때 기존 사이트 파일이 있고 실제 서비스되는 폴더가 그 값입니다. 접속하자마자 웹 루트면 `./`, 하위폴더에 넣으려면 `/www/iuccs/` 처럼.

### (3) 서버에 `.env` 직접 올리기 (FTP)
`.env.example`을 복사해 `.env`로 만들고 DB 정보·`INIT_PASSWORD`·`MIGRATE_TOKEN`을 채운 뒤,
FTP로 **웹 루트(`FTP_SERVER_DIR`와 같은 위치)** 에 업로드합니다.
`.env`는 워크플로 업로드에서 제외되므로 배포해도 덮어써지지 않습니다.

---

## 3. 배포

```bash
git add . && git commit -m "deploy" && git push origin main
```

저장소 **Actions 탭**에서 FTP 업로드 로그를 확인합니다.

### DB 테이블 생성 (마이그레이션)
FTP는 서버에서 명령을 실행하지 못하므로, 브라우저로 **한 번** 호출합니다:

```
https://도메인/migrate.php?token=(설정한 MIGRATE_TOKEN)
```

`[migrate] apply 001_init.sql` 같은 줄이 보이면 테이블이 생성된 것입니다.
이미 적용된 마이그레이션은 자동 스킵됩니다(재호출 안전).
`SITE_URL`+`MIGRATE_TOKEN` Secret을 넣어두면 배포 때 워크플로가 자동으로 호출합니다.

---

## 4. 접속 & 최초 로그인

- 관제 대시보드 : `https://도메인/public/dashboard/` — 계정 `admin` / 비밀번호 `INIT_PASSWORD`
- 조종자 앱 : `https://도메인/public/operator/` — 팀 번호 `03` 등 / 비밀번호 `INIT_PASSWORD`

> ⚠️ 최초 로그인 후 **반드시 비밀번호를 변경**하세요.

시드로 3개 팀(01·03·05)과 샘플 기체가 들어갑니다.

---

## 5. DB 스키마

`teams` · `users` · `drones` · `missions` · `reports` · `fire_requests` · `activity_log` · `sessions`
— 팀/자산, 사용자(역할기반), 임무 지시(승인·ROE 게이트), 결과 보고, 화력요청, 감사 로그, 세션.

새 마이그레이션은 `db/migrations/003_xxx.sql` 처럼 번호를 올려 추가하면 다음 `migrate.php` 호출 때 자동 적용됩니다.

---

## 6. 주의

- **보안** : `.htaccess`가 `.env`·`db/`·`src/`·`config/` 직접 접근을 차단합니다(Apache 기준·카페24/가비아 OK).
- **HTTPS** : 로그인 토큰이 오가므로 SSL을 반드시 켜세요.
- **FTPS** : 보안 FTP를 쓰면 워크플로의 `protocol: ftp` 를 `ftps` 로 바꾸세요.
- **PHP** : 7.4 이상.
- 이 저장소는 핵심 루프(로그인–상태–임무–보고–화력)가 실제 동작하는 기반입니다. 지도(COP)·사진 업로드·계정 관리 화면은 이어서 확장하면 됩니다.

---

*본 시스템은 지휘통제(C2) 소프트웨어이며, 공격·화력 등 치명 결심에는 사람의 승인과 교전규칙(ROE) 확인 절차를 두도록 설계되어 있습니다.*
