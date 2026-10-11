---

## API untuk Flutter (sisi pelapor/citizen)

Base URL: `https://your-domain/api/v1`

### Auth

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `POST` | `/auth/register` | ✗ | Daftar akun citizen. Body: `name`, `email`, `password`, `password_confirmation` |
| `POST` | `/auth/login` | ✗ | Login. Body: `email`, `password`. Returns `token` |
| `POST` | `/auth/logout` | Bearer token | Revoke token |
| `GET` | `/auth/me` | Bearer token | Profil user aktif |

### Referensi

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/categories` | Bearer token | Daftar kategori aktif (`id`, `code`, `name`) |

### Alur laporan (v2 — submission pipeline)

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `POST` | `/report-submissions` | Bearer token | Kirim laporan baru. `multipart/form-data`. Returns `202` + `submission_id` |
| `GET` | `/report-submissions/{id}` | Bearer token | Polling status pemeriksaan. Returns status + kandidat duplikat jika ada |
| `POST` | `/report-submissions/{id}/duplicate-response` | Bearer token | Citizen menjawab kandidat duplikat (`same_issue` / `different_issue`) |

**Request `POST /report-submissions`:**
```
Content-Type: multipart/form-data

description         (required, 20–2000 char)
occurred_at         (required, ISO-8601)
selected_category_id OR category_code  (optional)
latitude + longitude  (optional, harus berpasangan)
address_text        (optional)
gps_accuracy_m      (optional)
idempotency_key     (optional, max 64 char — cegah submit ganda dari retry)
attachments[]       (1–3 file, JPEG/PNG/WebP, max 10 MB per file)
```

**Response 202:**
```json
{
  "data": {
    "submission_id": "01JEXAMPLEULID",
    "status": "queued_for_moderation",
    "message": "Laporan sedang memeriksa foto dan informasi yang dikirim."
  }
}
```

**Polling `GET /report-submissions/{id}` — contoh status `duplicate_review`:**
```json
{
  "data": {
    "submission_id": "01JEXAMPLEULID",
    "status": "duplicate_review",
    "moderation": { "decision": "approved", "category": "road_damage" },
    "possible_matches": [
      { "report_id": "123", "distance_m": 34.2, "reason": "Lokasi dan tipe serupa" }
    ],
    "report_id": null
  }
}
```

**Status yang perlu Flutter tangani:**

| `status` | UI yang disarankan |
|---|---|
| `queued_for_moderation` | Spinner "Sedang memeriksa foto…" |
| `moderating` | Spinner |
| `queued_for_duplicate_check` | Spinner "Memeriksa laporan serupa…" |
| `checking_duplicates` | Spinner |
| `duplicate_review` | Tampilkan kandidat, minta respons citizen |
| `manual_review` | "Laporan sedang ditinjau petugas" |
| `accepted` | "Laporan diterima" + tampilkan `report_id` |
| `rejected` | "Laporan tidak dapat diproses" |
| `linked_to_existing_report` | "Laporan terhubung ke laporan yang sudah ada" |
| `processing_failed` | "Terjadi kesalahan, coba lagi nanti" |

### Laporan resmi (setelah accepted)

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/reports` | Bearer token | Daftar laporan milik sendiri (citizen hanya lihat miliknya) |
| `GET` | `/reports/{id}` | Bearer token | Detail laporan + status history + AI insight publik |
| `POST` | `/reports/{id}/feedback` | Bearer token | Kirim feedback (`satisfied`/`unsatisfied`/`other`) |
| `GET` | `/attachments/{id}` | Bearer token | Download foto (private, harus authorized) |

---

## Alur integrasi AI (backend, tidak langsung dari Flutter)

Flutter **tidak** memanggil Langflow. Semua AI diproses di queue backend Laravel.

```
Flutter
  │  POST /report-submissions
  ▼
Laravel (simpan submission + foto ke quarantine)
  │  dispatch RunImageGateJob → queue
  ▼
[QUEUE] RunImageGateJob
  │  POST LANGFLOW_BASE_URL/api/v1/run/LANGFLOW_IMAGE_GATE_FLOW_ID
  │  payload: description, category, occurred_at, image_input
  │  ← hasil: moderation_decision (approved/rejected/manual_review)
  │
  ├─ approved → dispatch RunDuplicateIntelligenceJob
  ├─ rejected → submission.status = rejected, foto dihapus
  └─ manual_review / error → submission.status = manual_review
  ▼
[QUEUE] RunDuplicateIntelligenceJob
  │  Laravel cari kandidat laporan dalam radius DUPLICATE_RADIUS_METERS (default 100m)
  │  menggunakan Haversine SQL di MySQL
  │
  ├─ tidak ada kandidat → status = manual_review (officer finalize)
  │
  └─ ada kandidat → POST LANGFLOW_BASE_URL/api/v1/run/LANGFLOW_DUPLICATE_FLOW_ID
       payload: metadata submission + kandidat tersanitasi (tanpa PII)
       ← hasil: duplicate_decision + matches tervalidasi
       │
       ├─ no_match → status = manual_review
       └─ possible_duplicate/uncertain → status = duplicate_review
                                          (Flutter ditampilkan kandidat)
Flutter polling GET /report-submissions/{id}
```

**Env yang harus diisi agar AI aktif:**
```dotenv
LANGFLOW_BASE_URL=https://your-langflow-server
LANGFLOW_API_KEY=your-key
LANGFLOW_IMAGE_GATE_FLOW_ID=uuid-flow-a
LANGFLOW_DUPLICATE_FLOW_ID=uuid-flow-b
```

Jika tidak dikonfigurasi: kedua job route ke `manual_review` dan catat `FLOW_NOT_CONFIGURED` di `ai_analysis_runs`. Report tidak dibuat otomatis, tidak ada data palsu.