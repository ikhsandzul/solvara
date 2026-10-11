# Solvara — Backend Laravel MVP

AI-Powered Civic Intelligence Platform. REST API for Flutter citizen app + Livewire officer dashboard.

---

## Stack

- **PHP** ^8.3 · **Laravel** 13 · **Laravel Sanctum** 4 · **Livewire** 4
- **Database** MySQL (production) / SQLite in-memory (tests)
- **Queue** Laravel database queue (`sync` in tests)
- **Storage** Laravel `private` disk for all attachments
- **Frontend** Blade + Livewire 4 + Tailwind CSS v4

---

## Quick start (local)

```bash
# 1. Copy env and generate key
cp .env.example .env
php artisan key:generate

# 2. Configure DB in .env (MySQL recommended)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=solvara_db
# DB_USERNAME=root
# DB_PASSWORD=

# 3. Install PHP dependencies
composer install

# 4. Install Node dependencies and build dashboard assets
npm install && npm run build

# 5. Create storage link (for public assets if needed)
php artisan storage:link

# 6. Run migrations
php artisan migrate

# 7. Seed demo accounts and categories
php artisan db:seed

# 8. Start server
php artisan serve

# 9. Start queue worker (required for AI analysis jobs)
php artisan queue:work
```

### Demo accounts (after seeding)

| Email | Password | Role |
|---|---|---|
| `officer@solvara.id` | `password` | officer |
| `admin@solvara.id` | `password` | admin |
| `warga@solvara.id` | `password` | citizen |

Officer dashboard: `http://localhost:8000/dashboard/login`

---

## Running tests

Tests use SQLite in-memory — no extra DB setup needed.

```bash
php artisan test
```

Expected: **72 passed, 1 skipped** (skipped = `ExampleTest` which requires a Vite build).

---

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `APP_KEY` | Yes | `php artisan key:generate` |
| `DB_CONNECTION` | Yes | `mysql` or `sqlite` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | MySQL only | Standard Laravel DB config |
| `QUEUE_CONNECTION` | Yes | `database` for production; `sync` for local dev |
| `SESSION_DRIVER` | Yes | `database` (default); `file` for simple local dev |
| `LANGFLOW_BASE_URL` | For AI | Base URL of Langflow server, e.g. `https://langflow.ibm.com/api/v1/run/` |
| `LANGFLOW_API_KEY` | For AI | API key from Langflow panel — **never commit real values** |
| `LANGFLOW_IMAGE_GATE_FLOW_ID` | For AI | Flow A: image content gate |
| `LANGFLOW_DUPLICATE_FLOW_ID` | For AI | Flow B: duplicate intelligence |
| `LANGFLOW_TIMEOUT_SECONDS` | Optional | Default `45` |
| `LANGFLOW_MAX_RETRIES` | Optional | Default `3` |
| `LANGFLOW_CONNECT_TIMEOUT_SECONDS` | Optional | Default `5` |
| `REPORT_UPLOAD_MAX_KB` | Optional | Max image size per file, default `10240` (10 MB) |
| `REPORT_MAX_IMAGES` | Optional | Max images per submission, default `3` |
| `REPORT_SUBMISSION_TEMP_TTL_HOURS` | Optional | Temp submission TTL before expiry, default `24` |
| `REPORT_TEMP_DISK` | Optional | Disk for temp uploads, default `private` |
| `REPORT_PERMANENT_DISK` | Optional | Disk for accepted report photos, default `private` |
| `DUPLICATE_RADIUS_METERS` | Optional | Haversine search radius, default `100` |
| `DUPLICATE_MAX_CANDIDATES` | Optional | Max nearby candidates sent to AI, default `10` |
| `DUPLICATE_ACTIVE_LOOKBACK_DAYS` | Optional | Default `180` |
| `DUPLICATE_HISTORY_LOOKBACK_DAYS` | Optional | Default `365` |

Secrets must never appear in source code. Use `.env` or a secret manager.

---

## API reference

See [`API_DOC.md`](API_DOC.md) for full endpoint details, request/response shapes, and error codes.

---

## Status flow

```
submitted → accepted → in_progress → resolved_pending_verification → verified
                                              ↕
                                           reopened
              ↓
           rejected
```

Every transition is written to `report_status_histories` (append-only, inside a DB transaction). Officers only can change status.

---

## AI / Langflow integration (v2 pipeline)

Submissions pass through two async jobs before becoming official reports:

1. **Flow A — Image Gate** (`RunImageGateJob`): validates photo content. Result: `approved`, `rejected`, or `manual_review`.
2. **Flow B — Duplicate Intelligence** (`RunDuplicateIntelligenceJob`): checks spatial + semantic similarity against existing reports. Result: `no_duplicate`, `possible_duplicate`, or `manual_review`.

If either flow is unconfigured or times out:
- Job falls back to `manual_review` — an officer decides manually.
- No submission is silently lost.
- `analysis_runs` table records every attempt with `status` and raw response.

AI never changes report status. Officers retain final authority.

---

## Key files

| Path | Purpose |
|---|---|
| `routes/api.php` | All REST API routes (`/api/v1`) |
| `routes/web.php` | Officer dashboard routes (`/dashboard`) |
| `app/Models/` | All Eloquent models |
| `app/Policies/ReportPolicy.php` | view / create / updateStatus / verify / feedback gates |
| `app/Services/LangflowImageGateClient.php` | Flow A HTTP client |
| `app/Services/LangflowDuplicateClient.php` | Flow B HTTP client |
| `app/Services/LangflowResponseNormalizer.php` | Validates + strips hallucinated IDs from AI response |
| `app/Services/PriorityScoreService.php` | Deterministic scoring (rules v1.0) |
| `app/Services/ReportStatusService.php` | Validated status transitions + history |
| `app/Services/FinalizeReportSubmissionService.php` | accept / reject / linkToExisting submission |
| `app/Services/FindNearbyReportCandidates.php` | Haversine SQL spatial search |
| `app/Jobs/RunImageGateJob.php` | Async: Flow A |
| `app/Jobs/RunDuplicateIntelligenceJob.php` | Async: Flow B |
| `app/Jobs/ExpireTemporarySubmissionsJob.php` | Scheduled hourly: removes stale submissions |
| `app/Http/Controllers/Api/V1/` | Auth, Category, Report, Attachment, ReportSubmission controllers |
| `app/Http/Controllers/Api/V1/Officer/` | OfficerReportController, OfficerSubmissionController |
| `app/Http/Controllers/Dashboard/` | Web auth + attachment controllers |
| `app/Livewire/` | DashboardOverview, ReportsList, ReportDetail, ModerationQueue, DuplicateQueue, ResolutionVerification |
| `app/Http/Requests/` | Form request validators |
| `app/Http/Resources/` | API response resources |
| `config/langflow.php` | Langflow config (env-driven) |
| `config/solvara.php` | Upload limits, spatial config |
| `database/migrations/` | 18 migrations |
| `database/seeders/DatabaseSeeder.php` | 5 categories + 3 demo users |
| `tests/Feature/Api/` | AuthTest, ReportTest, OfficerTest, LangflowTest, PriorityScoreTest |
| `tests/Feature/Api/V2/` | SubmissionLifecycleTest, ImageGateJobTest, DuplicateIntelligenceJobTest, FinalizeAndCleanupTest |
| `tests/Feature/Dashboard/DashboardTest.php` | 16 officer dashboard tests |
