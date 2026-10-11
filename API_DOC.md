# Solvara — API Documentation

Base URL: `{APP_URL}/api/v1`

All authenticated requests require:
```
Authorization: Bearer {token}
Content-Type: application/json   (or multipart/form-data where noted)
```

All errors return:
```json
{
  "error": {
    "code": "VALIDATION_ERROR | UNAUTHENTICATED | FORBIDDEN | NOT_FOUND | HTTP_ERROR",
    "message": "Human-readable description.",
    "fields": { "field_name": ["Error message"] }
  }
}
```
`fields` is only present on `VALIDATION_ERROR` (HTTP 422).

---

## Authentication

### POST `/auth/register`

Public. Rate-limited: 10/min.

**Request body**
```json
{
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

**Response 201**
```json
{
  "data": {
    "token": "1|xxxxxx",
    "user": {
      "id": 1,
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "role": "citizen",
      "created_at": "2025-01-01T00:00:00Z"
    }
  }
}
```

---

### POST `/auth/login`

Public. Rate-limited: 10/min.

**Request body**
```json
{
  "email": "budi@example.com",
  "password": "secret123"
}
```

**Response 200** — same shape as register.

**Response 401** on wrong credentials.

---

### POST `/auth/logout`

Auth required.

**Response 200**
```json
{ "data": { "message": "Logged out." } }
```

---

### GET `/auth/me`

Auth required.

**Response 200**
```json
{
  "data": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "role": "citizen",
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

---

## Categories

### GET `/categories`

Auth required.

**Response 200**
```json
{
  "data": [
    { "id": 1, "code": "JAL", "name": "Jalan Rusak", "is_active": true },
    { "id": 2, "code": "LAM", "name": "Lampu Mati", "is_active": true }
  ]
}
```

---

## Submission Pipeline (v2 — recommended for Flutter)

The v2 pipeline runs AI checks asynchronously before creating an official report.
Submit via `POST /report-submissions`, then poll `GET /report-submissions/{id}` for status.

### Submission status values

| Status | Meaning |
|---|---|
| `received` | Stored, jobs queued |
| `queued_for_moderation` | Waiting for image gate |
| `moderating` | Image gate running |
| `manual_review` | Officer must review |
| `duplicate_review` | Possible duplicate found |
| `accepted` | Converted to official report |
| `rejected` | Rejected by AI or officer |
| `processing_failed` | All retries exhausted |
| `expired` | Exceeded TTL without resolution |

---

### POST `/report-submissions`

Auth required (citizen). Rate-limited: 20/min.
Content-Type: `multipart/form-data`

**Fields**

| Field | Type | Required | Notes |
|---|---|---|---|
| `category_id` | integer | Yes | Must be active category |
| `title` | string | Yes | 5–200 chars |
| `description` | string | Yes | 20–5000 chars |
| `latitude` | numeric | No | -90 to 90 |
| `longitude` | numeric | No | -180 to 180 |
| `address_text` | string | No | Max 500 chars |
| `occurred_at` | date | No | ISO 8601, not in future |
| `photos[]` | file | No | JPEG/PNG/WebP, max `REPORT_UPLOAD_MAX_KB` each, max `REPORT_MAX_IMAGES` files |
| `idempotency_key` | string | No | UUID — repeat request returns original submission |

**Response 202**
```json
{
  "data": {
    "id": "01HZ...",
    "status": "received",
    "title": "Jalan Berlubang di Depan Sekolah",
    "received_at": "2025-01-01T00:00:00Z"
  }
}
```

---

### GET `/report-submissions/{id}`

Auth required. Citizens see only own submissions; officers see all.

**Response 200**
```json
{
  "data": {
    "id": "01HZ...",
    "status": "accepted",
    "title": "Jalan Berlubang di Depan Sekolah",
    "category_id": 1,
    "description": "...",
    "latitude": -6.2,
    "longitude": 106.8,
    "address_text": "Jl. Mawar No. 5",
    "occurred_at": "2025-01-01",
    "received_at": "2025-01-01T00:00:00Z",
    "accepted_report_id": 42,
    "moderation_result": {
      "passed": true,
      "rejection_reason": null,
      "confidence": 0.97
    },
    "similarity_matches": []
  }
}
```

`accepted_report_id` is non-null when `status = accepted`. Use it to fetch the official report via `GET /reports/{id}`.

---

### POST `/report-submissions/{id}/duplicate-response`

Auth required (citizen). Rate-limited: 10/min.
Used when citizen wants to confirm or dispute a duplicate flag before officer review.

**Request body**
```json
{
  "citizen_response": "not_duplicate",
  "note": "Ini beda lubang, yang itu sudah 3 bulan lalu."
}
```

`citizen_response` values: `not_duplicate` | `is_duplicate`.

**Response 200**
```json
{ "data": { "message": "Respons dicatat." } }
```

---

## Reports (v1 — direct, kept for compatibility)

> For new Flutter builds, prefer the v2 submission pipeline above.

### POST `/reports`

Auth required (citizen). Rate-limited: 20/min.
Content-Type: `multipart/form-data`

**Fields** — same as `POST /report-submissions` minus `idempotency_key`.

**Response 201**
```json
{
  "data": {
    "id": 1,
    "public_code": "SOL-2025-0001",
    "status": "submitted",
    "title": "...",
    "description": "...",
    "category": { "id": 1, "name": "Jalan Rusak" },
    "user": { "id": 3, "name": "Budi" },
    "latitude": -6.2,
    "longitude": 106.8,
    "address_text": "...",
    "occurred_at": "2025-01-01",
    "analysis_status": "queued",
    "attachments": [],
    "ai_analysis": null,
    "priority_score": null,
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-01T00:00:00Z"
  }
}
```

---

### GET `/reports`

Auth required. Citizens see only own reports. Officers see all.

**Query params**

| Param | Type | Notes |
|---|---|---|
| `page` | integer | Default 1 |
| `per_page` | integer | Default 15, max 50 |
| `status` | string | Filter by status |
| `category_id` | integer | Filter by category |

**Response 200**
```json
{
  "data": [ { /* ReportResource */ } ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

---

### GET `/reports/{id}`

Auth required. Citizens see only own reports; officers see all.

**Response 200** — full `ReportResource` (includes `attachments`, `ai_analysis`, `priority_score`).

**Response 403** if citizen tries to access another citizen's report.

---

### POST `/reports/{id}/feedback`

Auth required. Owner only. Rate-limited: 5/min.

**Request body**
```json
{
  "rating": 4,
  "comment": "Sudah diperbaiki, terima kasih."
}
```

`rating`: integer 1–5.

**Response 201**
```json
{ "data": { "message": "Terima kasih atas masukan Anda." } }
```

---

## Attachments

### GET `/attachments/{id}`

Auth required. Owner or officer. Streams the file from private disk.

**Response 200** — binary file stream with correct `Content-Type`.

**Response 403 / 404** if not authorised or file missing.

---

## Officer endpoints

All officer endpoints require `role = officer` or `role = admin`.

---

### GET `/officer/dashboard/summary`

**Response 200**
```json
{
  "data": {
    "by_status": { "submitted": 5, "in_progress": 3, "verified": 12 },
    "by_category": [ { "category": "Jalan Rusak", "total": 8 } ],
    "total_reports": 30
  }
}
```

---

### GET `/officer/reports`

**Query params**

| Param | Type | Notes |
|---|---|---|
| `page` | integer | Default 1 |
| `per_page` | integer | Default 20, max 100 |
| `status` | string | Filter |
| `category_id` | integer | Filter |
| `search` | string | Matches `public_code`, `title`, `description` |
| `sort_by` | string | `created_at` (default), `priority_score` |
| `sort_dir` | string | `desc` (default), `asc` |

**Response 200** — paginated list of `ReportResource`.

---

### PATCH `/officer/reports/{id}/status`

**Request body**
```json
{
  "status": "in_progress",
  "note": "Sudah ditugaskan ke tim lapangan."
}
```

Allowed transitions:

| From | To |
|---|---|
| `submitted` | `accepted`, `rejected` |
| `accepted` | `in_progress`, `rejected` |
| `in_progress` | `resolved_pending_verification`, `rejected` |
| `resolved_pending_verification` | `verified`, `reopened` |
| `verified` | `reopened` |
| `reopened` | `accepted` |

Invalid transitions return HTTP 422.

**Response 200** — updated `ReportResource`.

---

### POST `/officer/reports/{id}/verification`

**Request body**
```json
{
  "decision": "verified",
  "note": "Foto bukti perbaikan diterima."
}
```

`decision` values: `verified` | `reopen`.

Report must be in `resolved_pending_verification` (for `verified`) or `verified`/`resolved_pending_verification` (for `reopen`).

**Response 200** — updated `ReportResource`.

---

### POST `/officer/report-submissions/{id}/review`

Review a submission in `manual_review` or `duplicate_review`.

**Request body**
```json
{
  "decision": "approve_new",
  "note": "Foto valid, bukan duplikat.",
  "duplicate_of_report_id": null
}
```

`decision` values: `approve_new` | `confirm_duplicate` | `reject`.

When `decision = confirm_duplicate`, `duplicate_of_report_id` (integer) is required.

**Response 200**
```json
{ "data": { "message": "Keputusan moderasi dicatat." } }
```

---

## Response shapes reference

### ReportResource

```json
{
  "id": 1,
  "public_code": "SOL-2025-0001",
  "status": "in_progress",
  "title": "Jalan Berlubang",
  "description": "...",
  "category": { "id": 1, "name": "Jalan Rusak", "code": "JAL" },
  "user": { "id": 3, "name": "Budi Santoso" },
  "latitude": -6.2088,
  "longitude": 106.8456,
  "address_text": "Jl. Mawar No. 5",
  "occurred_at": "2025-01-01",
  "analysis_status": "completed",
  "attachments": [
    {
      "id": 10,
      "kind": "before_repair",
      "mime_type": "image/jpeg",
      "size_bytes": 204800,
      "url": "/api/v1/attachments/10"
    }
  ],
  "ai_analysis": {
    "suggested_category": "Jalan Rusak",
    "urgency_level": "high",
    "summary": "...",
    "recommendations": "...",
    "status": "completed"
  },
  "priority_score": {
    "final_score": 78,
    "urgency_score": 30,
    "impact_score": 25,
    "wait_time_score": 23
  },
  "created_at": "2025-01-01T00:00:00Z",
  "updated_at": "2025-01-02T00:00:00Z"
}
```

### Error codes

| Code | HTTP | Meaning |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Field validation failed |
| `UNAUTHENTICATED` | 401 | No token or token revoked |
| `FORBIDDEN` | 403 | Authenticated but not authorised |
| `NOT_FOUND` | 404 | Resource does not exist |
| `TOO_MANY_REQUESTS` | 429 | Rate limit exceeded |
| `HTTP_ERROR` | 5xx | Unexpected server error |
