# Solvara — Backend Laravel MVP

AI-Powered Civic Intelligence Platform. REST API for Flutter app and officer dashboard.

---

## Stack

- **PHP** ^8.3 · **Laravel** 13 · **Laravel Sanctum** 4
- **Database** MySQL (production) / SQLite in-memory (tests)
- **Queue** Laravel database queue (sync in test)
- **Storage** Laravel private disk for attachments

---

## Quick start (local)

```bash
# 1. Copy env and generate key
cp .env.example .env
php artisan key:generate

# 2. Configure DB in .env (MySQL recommended; SQLite works for dev)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=solvara
# DB_USERNAME=root
# DB_PASSWORD=

# 3. Install dependencies
composer install

# 4. Run migrations
php artisan migrate

# 5. Seed demo accounts and categories
php artisan db:seed

# 6. Start server
php artisan serve

# 7. Start queue worker (required for AI analysis)
php artisan queue:work
```

---

## Running tests

Tests use SQLite in-memory; no extra DB setup needed.

```bash
php artisan test
```

Expected result: **32 passed, 1 skipped** (skipped = Vite manifest check, unrelated to backend).

---

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `APP_KEY` | Yes | Generated with `php artisan key:generate` |
| `DB_CONNECTION` | Yes | `mysql` or `sqlite` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | For MySQL | Standard Laravel DB config |
| `QUEUE_CONNECTION` | Yes | `database` for production; `sync` for local dev |
| `FILESYSTEM_DISK` | Yes | `local` (private attachments); use `s3` + AWS vars for cloud |
| `LANGFLOW_SERVER_URL` | For AI | e.g. `https://your-langflow.ibm.com` |
| `LANGFLOW_FLOW_ID` | For AI | UUID of the flow |
| `LANGFLOW_API_KEY` | For AI | API key from Langflow panel |
| `LANGFLOW_TIMEOUT_SECONDS` | Optional | Default `30` |
| `MAX_ATTACHMENT_KB` | Optional | Default `5120` (5 MB per image) |

Secrets must never appear in source code. Use `.env` or a secret manager.

---

## API contract (`/api/v1`)

| Method | Endpoint | Role | Description |
|---|---|---|---|
| POST | `/auth/register` | Public | Register citizen |
| POST | `/auth/login` | Public | Login → token |
| POST | `/auth/logout` | Any auth | Revoke token |
| GET | `/auth/me` | Any auth | Current user profile |
| GET | `/categories` | Any auth | Active categories |
| POST | `/reports` | citizen | Create report (multipart) |
| GET | `/reports` | citizen (own) / officer | List reports |
| GET | `/reports/{id}` | owner / officer | Report detail |
| POST | `/reports/{id}/feedback` | owner only | Submit feedback |
| GET | `/attachments/{id}` | owner / officer | Download attachment (auth required) |
| GET | `/officer/dashboard/summary` | officer / admin | Stats by status & category |
| GET | `/officer/reports` | officer / admin | All reports with filter/sort/search |
| PATCH | `/officer/reports/{id}/status` | officer / admin | Change status + note |
| POST | `/officer/reports/{id}/verification` | officer / admin | Verify or reopen |

Full response shapes match the PRD contract. Errors always return:
```json
{"error": {"code": "...", "message": "...", "fields": {}}}
```

---

## Status flow

```
submitted → needs_review → accepted → in_progress → resolved_pending_verification → verified
                  ↓                                            ↕
               rejected ←──────────────────────────────── reopened
```

Every transition writes an append-only record to `report_status_histories` inside a DB transaction.

---

## AI / Langflow integration

- `AnalyzeReportJob` dispatched async after report creation.
- Sends sanitised payload (no PII, no photos) to `POST {LANGFLOW_SERVER_URL}/api/v1/run/{LANGFLOW_FLOW_ID}`.
- Retries up to 3×; on exhaustion sets `analysis_status = failed` — report stays intact.
- AI output is normalised and validated before storage in `ai_analyses`.
- AI never changes report status; only officers can.
- If `LANGFLOW_SERVER_URL` or `LANGFLOW_FLOW_ID` are not set, the job logs a warning and stops cleanly.

---

## Key files added

| Path | Purpose |
|---|---|
| `routes/api.php` | All API routes |
| `app/Models/` | User, Category, Report, AiAnalysis, PriorityScore, ReportAttachment, ReportStatusHistory, VerificationReview, ReportFeedback |
| `app/Policies/ReportPolicy.php` | view / create / updateStatus / verify / feedback gates |
| `app/Services/LangflowClient.php` | Langflow HTTP client + normalizer |
| `app/Services/PriorityScoreService.php` | Deterministic scoring (rules v1.0) |
| `app/Services/ReportStatusService.php` | Validated status transitions + history |
| `app/Jobs/AnalyzeReportJob.php` | Queue job for AI analysis |
| `app/Http/Controllers/Api/V1/` | Auth, Category, Report, Attachment controllers |
| `app/Http/Controllers/Api/V1/Officer/` | OfficerReportController |
| `app/Http/Requests/` | Form request validators |
| `app/Http/Resources/` | API response resources |
| `config/langflow.php` | Langflow config (env-driven) |
| `config/solvara.php` | App-specific config |
| `database/migrations/2026_10_11_*` | 9 new migrations |
| `tests/Feature/Api/` | AuthTest, ReportTest, OfficerTest, LangflowTest, PriorityScoreTest |
