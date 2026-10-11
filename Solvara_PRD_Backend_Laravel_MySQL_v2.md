# PRD Backend v2 — Solvara

**Komponen:** Laravel REST API + MySQL + Queue + Private File Storage  
**Versi:** 2.0 — menggantikan PRD Backend v1.0  
**Tanggal:** 11 Oktober 2026  
**Status:** Spesifikasi MVP untuk implementasi  
**Pengguna dokumen:** IBM Bob / AI coding agent, backend developer, integrator Langflow

> **Perubahan utama v2:** Solvara sekarang memeriksa foto sebelum membuat laporan resmi, mencari kandidat laporan di sekitar koordinat, dan menggunakan AI untuk membantu menilai kemungkinan duplikat. MySQL tetap menjadi sumber data utama. Metadata submission boleh dicatat sementara untuk melacak proses, tetapi record resmi pada tabel `reports` hanya dibuat setelah proses pemeriksaan selesai dan lolos kebijakan penerimaan.

## Instruksi untuk AI Coding Agent

Baca seluruh PRD ini sebelum coding. Audit repository yang sudah ada, versi PHP/Laravel, migration, konfigurasi auth, queue, file storage, test, serta implementasi terdahulu. Setelah audit singkat, **langsung implementasikan requirement MVP** secara bertahap; jangan berhenti di rencana atau contoh kode.

- Pertahankan codebase dan fitur yang masih berguna. Jangan menghapus atau mereset database tanpa izin.
- Gunakan versi Laravel/PHP yang sudah dipakai repository, kecuali ada blocker kompatibilitas yang nyata.
- Jangan menaruh secret Langflow atau AI model di source code atau Flutter.
- Jangan mengklaim integrasi AI sudah bekerja sebelum diuji terhadap flow Langflow yang benar-benar tersedia.
- Jika flow ID, provider/model, atau kredensial belum tersedia, implementasikan adapter, konfigurasi `.env.example`, mock untuk automated test, dan dokumentasikan blocker integrasi nyata.
- Jalankan migration dan test pada environment yang aman. Laporkan command dan hasil yang benar-benar dijalankan.
- Jika detail tidak ditentukan, pilih default aman, konsisten, dan terdokumentasi. Jangan meminta konfirmasi untuk keputusan implementasi rutin.

## 1. Ringkasan Produk

Solvara adalah platform civic intelligence untuk laporan fasilitas publik seperti jalan rusak, sampah menumpuk, drainase tersumbat, dan lampu jalan mati. Backend menjadi sumber kebenaran untuk identitas pengguna, metadata submission, laporan resmi, status penanganan, lampiran, hasil moderasi AI, kandidat duplikat, prioritas, dan audit trail.

### Tujuan MVP

1. Menerima foto, deskripsi, koordinat, dan waktu laporan dari Flutter secara aman.
2. Memeriksa validitas file dan menaruh foto pada private temporary storage.
3. Memanggil **Langflow Flow A — Image Gate** untuk moderasi gambar, relevansi masalah publik, klasifikasi, dan kecocokan foto dengan deskripsi.
4. Jika Flow A lolos, mencari kandidat laporan yang dekat secara geografis menggunakan Laravel + MySQL.
5. Jika kandidat tersedia, memanggil **Langflow Flow B — Duplicate Intelligence** untuk menilai apakah laporan baru mungkin duplikat atau masalah berulang.
6. Memutuskan pembuatan laporan resmi berdasarkan aturan backend dan/atau tinjauan manusia, bukan keputusan bebas dari LLM.
7. Menyediakan API konsisten untuk Flutter dan dashboard petugas.

## 2. Prinsip Arsitektur dan Batas Tanggung Jawab

- **Flutter:** mengambil foto, deskripsi, koordinat GPS atau lokasi manual, waktu kejadian, dan menampilkan status pemeriksaan.
- **Laravel:** autentikasi, validasi file, proses submission, antrian job, pencarian spasial, aturan transisi, keputusan penyimpanan, perhitungan prioritas, audit, dan integrasi antar-service.
- **MySQL:** menyimpan submission, laporan resmi, lokasi, metadata lampiran, hasil analisis yang tervalidasi, hubungan duplikat, dan riwayat status.
- **Private file/object storage:** menyimpan foto sementara dalam quarantine dan foto resmi setelah lolos. Hindari menyimpan binary foto langsung dalam tabel MySQL.
- **IBM Langflow Flow A:** analisis keamanan gambar, relevansi ke layanan publik, kategori, ringkasan visual, dan kecocokan foto dengan deskripsi.
- **IBM Langflow Flow B:** membandingkan submission dengan kandidat laporan yang sudah dipilih backend.
- **IBM Bob:** digunakan developer untuk menulis, mengubah, dan menguji kode. Bob bukan layanan runtime yang menerima setiap laporan warga.

**Penting tentang “sebelum masuk database”:** jangan membuat laporan resmi di tabel `reports` sebelum pemeriksaan selesai. Sistem tetap boleh menyimpan metadata minimum di `report_submissions` agar progress, retry, audit, dan status kepada pengguna dapat dikelola. Foto tetap berada di private temporary storage hingga lolos gerbang penerimaan.

## 3. Stack dan Konvensi

- Laravel REST API; gunakan versi yang sudah ada di repository.
- MySQL; verifikasi versi dan fungsi spasial yang tersedia di environment.
- Laravel Sanctum atau mekanisme auth API yang sudah dipakai proyek.
- Laravel Queue; gunakan database queue untuk MVP apabila belum ada queue backend lain.
- Laravel Storage untuk file privat; production dapat menggunakan object storage.
- Service adapter khusus untuk Langflow; Flutter tidak boleh memanggil Langflow langsung.
- PHPUnit/Pest mengikuti konvensi repository.
- Prefix endpoint: `/api/v1`.
- Timestamp API: ISO-8601 dengan timezone eksplisit. Simpan waktu server sebagai waktu penerimaan resmi dan pisahkan dari `occurred_at` atau waktu yang diklaim perangkat.

## 4. Role dan Hak Akses

| Role | Hak utama |
|---|---|
| `citizen` | Membuat submission, melihat submission miliknya, melihat laporan sendiri dan status publik, merespons saran kemungkinan duplikat. |
| `officer` | Meninjau submission manual/duplikat, melihat laporan sesuai cakupan tugas, mengubah status laporan, mengunggah bukti tindak lanjut, dan memverifikasi hasil. |
| `admin` | Mengelola konfigurasi operasional, kategori, role, serta keputusan eskalasi sesuai kebijakan. |

Semua operasi harus memeriksa role dan kepemilikan/cakupan akses. Menebak ID tidak boleh memberikan akses ke submission, foto, prompt, atau catatan internal milik pihak lain.

## 5. Alur Submission sampai Laporan Resmi

### 5.1 Menerima submission

1. Flutter mengirim `POST /api/v1/report-submissions` dengan multipart form-data.
2. Laravel memvalidasi autentikasi, deskripsi, kategori pilihan bila ada, koordinat, waktu, MIME/type gambar, ukuran, jumlah file, dan rate limit.
3. File disimpan dengan nama acak ke private temporary storage. Buat metadata pada `report_submissions` dengan status `received`/`queued_for_moderation`, hash file, ukuran, MIME yang terdeteksi, dan `expires_at`.
4. Laravel dispatch job `RunImageGateJob` dan merespons `202 Accepted` berisi submission ID dan status awal. Jangan menunggu inferensi gambar dalam request HTTP.

### 5.2 Flow A — Image Gate

1. Job menandai submission `moderating` dan mencatat `ai_analysis_runs`.
2. Backend mengirim deskripsi, kategori pilihan, waktu, dan referensi foto ke Langflow Flow A melalui adapter yang sesuai dengan konfigurasi Langflow.
3. Backend menormalisasi dan memvalidasi hasil Flow A. Output minimum: `moderation_decision` (`approved`, `rejected`, `manual_review`), `is_civic_issue`, `suggested_category`, `image_description`, `description_match`, `confidence_band`, dan alasan singkat.
4. Jika aman/relevan dan output valid, submission menjadi `queued_for_duplicate_check`.
5. Jika tidak pantas/tidak relevan dengan keyakinan yang memenuhi kebijakan teruji, submission dapat ditolak dan foto temporary dijadwalkan untuk dihapus. Catat kode alasan umum, jangan menyimpan atau menampilkan deskripsi eksplisit konten sensitif.
6. Jika hasil ambigu, bertentangan, confidence tidak memadai, model tidak tersedia, atau output invalid, ubah menjadi `manual_review` atau `processing_failed` sesuai penyebab. Jangan auto-reject karena timeout/error teknis.

**Moderation threshold:** jangan mengasumsikan angka confidence dari LLM sebagai probabilitas terkalibrasi. Threshold auto-approve/auto-reject harus konfigurabel dan diuji pada validation set yang relevan. Sampai threshold tervalidasi, gunakan pemeriksaan manual untuk hasil yang tidak pasti.

### 5.3 Pencarian kandidat di MySQL

Setelah Flow A lolos:

1. Laravel mengambil laporan resmi di sekitar koordinat, menggunakan pencarian bounding box untuk mempersempit data lalu menghitung jarak sebenarnya.
2. Radius awal yang dapat dikonfigurasi untuk MVP: `DUPLICATE_RADIUS_METERS=100`; ini nilai awal uji, bukan aturan universal.
3. Batasi kandidat yang dikirim ke Flow B (misalnya 5–10 kandidat terbaik). Jangan mengirim PII pelapor.
4. Prioritaskan laporan aktif sebagai kandidat duplikat. Laporan selesai dapat disertakan sebagai konteks historis untuk mendeteksi masalah berulang, tetapi jangan otomatis menyamakan “berulang” dengan “duplikat”.
5. Jika tidak ada kandidat yang relevan, lewati Flow B dan lanjut ke kebijakan penerimaan laporan resmi.

Untuk implementasi MySQL sederhana, koordinat dapat disimpan pada `latitude`/`longitude` DECIMAL. Gunakan bounding-box filter dan fungsi jarak seperti `ST_Distance_Sphere(POINT(longitude, latitude), POINT(:longitude, :latitude))` bila didukung versi MySQL pada environment. Uji urutan koordinat, satuan meter, dan rencana query. Jika repository sudah memakai tipe geospasial `POINT`, ikuti konvensi tersebut dan tambahkan spatial index yang valid.

Konfigurasi yang disarankan:
- `DUPLICATE_RADIUS_METERS=100`
- `DUPLICATE_MAX_CANDIDATES=10`
- `DUPLICATE_ACTIVE_LOOKBACK_DAYS=180` (untuk membatasi kandidat aktif berumur panjang bila sesuai data)
- `DUPLICATE_HISTORY_LOOKBACK_DAYS=365` (konteks historis; sesuaikan untuk performa dan jenis masalah)

Jangan mengecualikan laporan aktif hanya karena terlalu lama. Definisikan secara eksplisit aturan waktu antara kandidat aktif dan histori yang selesai.

### 5.4 Flow B — Duplicate Intelligence

Input ke Langflow berisi metadata submission dan daftar kandidat yang sudah ditemukan Laravel: `report_id`, jarak meter, status, kategori, waktu, deskripsi yang disanitasi, dan ringkasan visual tersimpan bila tersedia. Untuk MVP, kirim ringkasan visual, bukan seluruh foto historis. Hanya kirim foto tambahan jika fitur perbandingan visual memang diimplementasikan dan aksesnya aman.

Output Flow B minimum:
- `duplicate_decision`: `no_match`, `possible_duplicate`, `uncertain`, atau `recurring_issue`;
- `matches`: daftar kandidat dengan `candidate_report_id`, `match_type`, `confidence_band`, dan alasan;
- `summary` singkat untuk petugas;
- `needs_human_review` boolean.

Backend harus memvalidasi bahwa setiap `candidate_report_id` dalam output memang termasuk kandidat yang dikirim. Abaikan/anggap invalid ID yang dibuat oleh AI. Jangan memakai output AI untuk menghapus laporan, menggabungkan record, atau mengganti status resmi secara otomatis.

### 5.5 Keputusan akhir

- **Lolos moderasi, tidak ada kandidat relevan:** Laravel membuat record `reports`, memindahkan foto dari temporary storage ke private storage permanen, membuat attachment, dan menandai submission `accepted` dengan `report_id`.
- **Kemungkinan duplikat:** submission menjadi `duplicate_review`. Jangan membuat laporan resmi baru sebelum keputusan dibuat. Tampilkan kandidat yang relevan kepada warga dan/atau petugas sesuai desain produk.
- **Warga mengonfirmasi masalah yang sama:** simpan relasi submission ke laporan yang ada dan tandai sebagai laporan terkait/dukungan; jangan membuat report duplikat. Keputusan ini tidak mengubah status laporan utama.
- **Warga menyatakan masalah berbeda:** ubah ke review petugas atau lanjut sesuai aturan kebijakan; jangan langsung bypass pemeriksaan.
- **Petugas menyetujui laporan baru:** Laravel membuat report resmi dan memindahkan foto.
- **Petugas mengonfirmasi duplikat:** catat hubungan duplikat, jangan membuat report resmi kedua.
- **Moderasi perlu review:** foto tetap private; petugas menentukan approve/reject. Keputusan tersimpan dengan actor dan alasan.
- **Integrasi AI gagal:** status submission `processing_failed` atau `manual_review`; simpan error code, berikan mekanisme retry, dan jangan menganggapnya lolos atau menolak secara otomatis.

## 6. Status yang Dikelola Backend

### 6.1 `report_submissions.status`

- `received`
- `queued_for_moderation`
- `moderating`
- `queued_for_duplicate_check`
- `checking_duplicates`
- `duplicate_review`
- `manual_review`
- `accepted`
- `rejected`
- `linked_to_existing_report`
- `processing_failed`
- `expired`

Buat state transition terpusat pada service/domain layer. Jangan mengubah status bebas dari controller.

### 6.2 `reports.status`

- `submitted`
- `accepted`
- `in_progress`
- `resolved_pending_verification`
- `verified`
- `reopened`
- `rejected`

`verified` hanya dapat ditetapkan petugas berwenang melalui endpoint verifikasi. Perubahan status wajib ditulis ke `report_status_histories`. Status pipeline AI harus dipisahkan dari status pelayanan laporan.

### 6.3 Status setiap run AI

- `queued`
- `processing`
- `completed`
- `failed`

Simpan tipe flow (`image_gate`/`duplicate_intelligence`), flow version, durasi, response schema version, error code, dan percobaan. Simpan output JSON yang disanitasi; jangan menyimpan kredensial, signed URL permanen, atau raw file.

## 7. Skema Database yang Dibutuhkan

Sesuaikan nama tabel dengan codebase yang ada. Jangan membuat tabel duplikat jika entitas setara sudah tersedia. Semua perubahan harus melalui migration dan foreign key/index yang sesuai.

| Tabel | Kolom penting dan tujuan |
|---|---|
| `users` | `id`, `name`, `email`/identifier, `password`, `role`, timestamps; password di-hash. Gunakan skema user yang sudah ada jika tersedia. |
| `categories` | `id`, `code` unique, `name`, `is_active`, timestamps. Contoh: `road_damage`, `waste`, `drainage`, `streetlight`, `other`. |
| `report_submissions` | `id` ULID/UUID, `user_id`, `description`, `selected_category_id` nullable, `latitude`, `longitude`, `address_text`, `occurred_at`, `received_at`, `status`, `temporary_attachment_path`, `detected_mime`, `size_bytes`, `sha256`, `gps_accuracy_m`, `idempotency_key` nullable, `expires_at`, `accepted_report_id` nullable, timestamps. Ini antrean/meta submission sebelum report resmi dibuat. |
| `reports` | `id`, `public_code` unique, `user_id`, `category_id`, `title`, `description`, `latitude`, `longitude`, `address_text`, `occurred_at`, `status`, `priority_score`/relasi skor, `accepted_from_submission_id` unique, timestamps. Hanya laporan resmi yang diterima. |
| `report_attachments` | `id`, `report_id`, `storage_path`, `kind` (`initial`, `after_repair`), `mime_type`, `size_bytes`, `sha256`, timestamps. Jangan simpan binary gambar di MySQL. |
| `report_moderation_results` | `id`, `submission_id`, `ai_analysis_run_id`, `moderation_decision`, `is_civic_issue`, `suggested_category`, `image_description`, `description_match`, `confidence_band`, `reasons_json`, `policy_version`, timestamps. Simpan alasan ringkas dan aman. |
| `report_similarity_matches` | `id`, `submission_id`, `candidate_report_id`, `distance_m`, `match_type`, `confidence_band`, `reason`, `review_status`, `reviewed_by` nullable, `review_note` nullable, timestamps. |
| `ai_analysis_runs` | `id`, `submission_id`, `flow_type`, `flow_id_or_version`, `provider`, `model_id` nullable, `status`, `attempt_number`, `duration_ms`, `response_schema_version`, `sanitized_output_json` nullable, `error_code` nullable, timestamps. |
| `report_status_histories` | `id`, `report_id`, `actor_id`, `old_status`, `new_status`, `note`, `created_at`. Append-only melalui aplikasi. |
| `verification_reviews` | `id`, `report_id`, `reviewer_id`, `decision` (`verified`, `reopen`), `note`, `created_at`. |
| `report_feedback` | `id`, `report_id`, `user_id`, `type`, `message` nullable, timestamps. |
| `jobs`, `failed_jobs`, `personal_access_tokens` | Tabel pendukung queue/Sanctum bila belum tersedia dan digunakan di repository. |

Gunakan unique constraint untuk `public_code`, `accepted_from_submission_id`, dan idempotency key sesuai scope. Pertimbangkan soft delete hanya jika sesuai dengan kebutuhan retensi dan audit; jangan menjadikannya pengganti kebijakan penghapusan foto temporary.

## 8. File Handling dan Retensi

- Validasi file di server; cek MIME berdasarkan isi file, ekstensi, ukuran maksimal, jumlah gambar, dan decoder yang tersedia. Jangan percaya nama/extensi dari client.
- Simpan foto submission pada private quarantine path dengan nama acak. Jangan expose public URL permanen selama pemeriksaan.
- Untuk Langflow, buat adapter pengiriman gambar yang sesuai dengan versi dan deployment yang dipakai: upload file/input file yang didukung, atau URL bertanda tangan yang privat dan berumur pendek jika konektivitas aman tersedia. Jangan menganggap URL publik wajib.
- Pindahkan file ke path report permanen hanya setelah submission diterima.
- Hapus foto temporary yang ditolak/kedaluwarsa sesuai retensi yang dapat dikonfigurasi; catat penghapusan tanpa menyimpan gambar di log.
- Batasi ukuran request, waktu proses, dan jumlah percobaan agar upload atau analisis tidak dapat menghabiskan resource.

Konfigurasi contoh untuk `.env.example`:

```dotenv
REPORT_UPLOAD_MAX_KB=10240
REPORT_MAX_IMAGES=3
REPORT_SUBMISSION_TEMP_TTL_HOURS=24
REPORT_TEMP_DISK=private
REPORT_PERMANENT_DISK=private
```

Sesuaikan batas ukuran dengan kebutuhan produk dan limit provider/model.

## 9. API Contract untuk Flutter dan Dashboard

Prefix: `/api/v1`. Kontrak API berikut menjadi baseline; implementasi boleh mengikuti konvensi codebase yang ada, tetapi perubahan harus didokumentasikan dan diselaraskan dengan Flutter/dashboard.

| Method + endpoint | Akses | Fungsi |
|---|---|---|
| `POST /auth/login` | Publik, rate-limited | Login dan mengembalikan token. |
| `POST /auth/logout` | Login | Revoke token aktif. |
| `GET /auth/me` | Login | Profil pengguna. |
| `GET /categories` | Login | Kategori aktif. |
| `POST /report-submissions` | Citizen | Membuat submission, multipart form-data. |
| `GET /report-submissions/{id}` | Pemilik atau petugas berwenang | Status pipeline, pesan aman, dan laporan resmi jika sudah diterima. |
| `POST /report-submissions/{id}/duplicate-response` | Pemilik submission | Menjawab bahwa kandidat sama/berbeda; keputusan final tetap mengikuti policy dan dapat memerlukan review. |
| `POST /officer/report-submissions/{id}/review` | Officer/admin sesuai policy | Approve sebagai report baru, konfirmasi duplikat, atau reject dengan alasan. |
| `GET /reports` | Citizen (scope milik sendiri)/officer/admin | Daftar laporan dengan filter dan pagination. |
| `GET /reports/{id}` | Pemilik atau petugas berwenang | Detail laporan dan timeline yang boleh ditampilkan. |
| `GET /officer/dashboard/summary` | Officer/admin | Ringkasan per status/kategori. |
| `GET /officer/reports` | Officer/admin | Daftar dan pencarian laporan petugas. |
| `PATCH /officer/reports/{id}/status` | Officer/admin sesuai policy | Transisi status dengan catatan. |
| `POST /officer/reports/{id}/resolution-evidence` | Officer/admin sesuai policy | Upload bukti setelah perbaikan. |
| `POST /officer/reports/{id}/verification` | Officer/admin sesuai policy | Verifikasi atau buka kembali laporan. |
| `POST /reports/{id}/feedback` | Pemilik laporan | Kirim feedback warga. |

### 9.1 Request membuat submission

`multipart/form-data`:

- `description` (wajib, 20–2000 karakter);
- `selected_category_id` atau `category_code` (opsional bila kategori otomatis, harus konsisten dengan Flutter);
- `latitude`, `longitude` (keduanya ada atau keduanya kosong; wajib sesuai kebijakan bila pelaporan berbasis GPS);
- `address_text` (opsional; dapat menjadi fallback jika koordinat tidak tersedia);
- `occurred_at` (ISO-8601, waktu kejadian yang diinformasikan pengguna);
- `client_captured_at` (opsional, metadata perangkat, tidak dipercaya sebagai waktu resmi);
- `gps_accuracy_m` (opsional, jika perangkat menyediakannya);
- `attachments[]` (1–3 gambar untuk MVP).

Server mencatat `received_at` sendiri. Jika lokasi kosong, submission boleh masuk ke pemeriksaan foto tetapi tidak menjalankan pencarian spasial berbasis koordinat; flow bisnis harus menggunakan alamat/manual review sesuai kebijakan.

### 9.2 Respons menerima submission

Respons `202 Accepted` karena moderasi dan pencarian duplikat berjalan asynchronous:

```json
{
  "data": {
    "submission_id": "01JEXAMPLEULID",
    "status": "queued_for_moderation",
    "message": "Laporan sedang memeriksa foto dan informasi yang dikirim."
  }
}
```

Ini hanya contoh kontrak. Setelah diproses, Flutter melakukan polling `GET /report-submissions/{id}` atau memakai mekanisme update yang telah diterapkan. Jangan mengembalikan URL file quarantine kepada warga.

### 9.3 Respons status submission

```json
{
  "data": {
    "submission_id": "01JEXAMPLEULID",
    "status": "duplicate_review",
    "moderation": {
      "decision": "approved",
      "category": "road_damage"
    },
    "possible_matches": [
      {
        "report_id": "01JREPORTEXAMPLE",
        "distance_m": 34.2,
        "reason": "Lokasi dan tipe masalah tampak serupa"
      }
    ],
    "report_id": null
  }
}
```

Jangan tampilkan catatan internal, PII warga lain, prompt model, atau alasan moderation yang berisi detail sensitif. Kandidat yang disajikan kepada warga harus hanya menampilkan informasi publik yang diizinkan.

### 9.4 Error API

Gunakan response JSON konsisten seperti `error.code`, `error.message`, dan `error.fields` jika relevan. Status umum: `401` unauthenticated, `403` unauthorized, `404` not found atau tidak terlihat oleh user, `422` validation, `429` rate limit, `5xx` server error. Jangan mengirim stack trace ke client.

## 10. Service, Job dan Modul yang Disarankan

Nama class adalah rekomendasi dan boleh disesuaikan dengan pola repository:

- `ReportSubmissionController`
- `ReportSubmissionService`
- `ImageValidationService`
- `PrivateReportMediaStorage`
- `RunImageGateJob`
- `FindNearbyReportCandidates` (query/service)
- `RunDuplicateIntelligenceJob`
- `FinalizeReportSubmissionService`
- `LangflowClient` / `LangflowImageGateClient` / `LangflowDuplicateClient`
- `LangflowResponseNormalizer`
- `ReportStatusTransitionService`
- `PriorityScoringService`
- `ExpireTemporarySubmissionsJob`

Controller harus tipis. Validasi request menggunakan Form Request; gunakan Policy/Gate untuk authorization; gunakan transaction ketika membuat `reports`, attachment, similarity relation, serta status history yang perlu konsisten.

## 11. Integrasi IBM Langflow

Konfigurasi lewat environment; nilai contoh tidak boleh berisi secret nyata:

```dotenv
LANGFLOW_BASE_URL=
LANGFLOW_API_KEY=
LANGFLOW_IMAGE_GATE_FLOW_ID=
LANGFLOW_DUPLICATE_FLOW_ID=
LANGFLOW_TIMEOUT_SECONDS=45
LANGFLOW_MAX_RETRIES=3
LANGFLOW_CONNECT_TIMEOUT_SECONDS=5
```

Flow ID, nama input file, bentuk `tweaks`, output path, dan payload aktual bergantung pada export/versi Langflow yang digunakan. Implementasikan adapter/config untuk memetakan field backend ke endpoint yang benar-benar diuji. Jangan hardcode asumsi satu struktur response jika Langflow dapat membungkus output berbeda.

### Contract Flow A — Image Gate

Payload konseptual:

```json
{
  "submission_id": "01JEXAMPLEULID",
  "description": "Jalan berlubang di depan sekolah",
  "selected_category": "road_damage",
  "occurred_at": "2026-10-11T08:30:00+07:00",
  "image_input": "resolved-by-private-file-adapter"
}
```

Hasil yang diharapkan:

```json
{
  "moderation_decision": "approved",
  "is_civic_issue": true,
  "suggested_category": "road_damage",
  "image_description": "Permukaan jalan tampak berlubang",
  "description_match": "match",
  "confidence_band": "high",
  "reasons": ["Gambar relevan dengan laporan kerusakan jalan"],
  "needs_human_review": false,
  "flow_version": "image_gate_v1"
}
```

### Contract Flow B — Duplicate Intelligence

Payload konseptual harus hanya berisi kandidat yang dipilih backend. Deskripsi dan ringkasan visual harus disanitasi; jangan masukkan PII.

```json
{
  "submission": {
    "description": "Jalan berlubang di depan sekolah",
    "category": "road_damage",
    "latitude": -6.2001,
    "longitude": 106.8167,
    "image_description": "Permukaan jalan tampak berlubang"
  },
  "candidates": [
    {
      "report_id": "01JREPORTEXAMPLE",
      "distance_m": 34.2,
      "status": "in_progress",
      "category": "road_damage",
      "description": "Ada lubang di jalan dekat gerbang sekolah",
      "image_description": "Jalan berlubang dekat gerbang"
    }
  ]
}
```

Hasil yang diharapkan:

```json
{
  "duplicate_decision": "possible_duplicate",
  "matches": [
    {
      "candidate_report_id": "01JREPORTEXAMPLE",
      "match_type": "same_issue",
      "confidence_band": "high",
      "reason": "Lokasi, kategori, dan gambaran masalah serupa"
    }
  ],
  "summary": "Ada satu laporan aktif yang patut ditinjau sebagai kemungkinan duplikat.",
  "needs_human_review": true,
  "flow_version": "duplicate_intelligence_v1"
}
```

Contoh di atas hanya kontrak. Backend wajib memvalidasi enum, schema, batas panjang, dan keanggotaan `candidate_report_id` pada candidate list. Jangan memperlakukan `confidence_band` sebagai probabilitas statistik.

### Toleransi kegagalan

- Timeout/5xx Langflow: retry terbatas dengan backoff; status tetap pending/failed dan terlihat oleh petugas.
- Output malformed: tandai run gagal, jangan anggap approved atau no-duplicate.
- Flow tidak dikonfigurasi: endpoint submit tidak boleh diam-diam mensimulasikan keberhasilan; status proses ditandai konfigurasi belum tersedia dan dicatat di dokumentasi.
- Untuk automated test, mock client, lalu sediakan integration test opt-in yang memerlukan endpoint/key nyata.

## 12. Pencatatan Prioritas dan Insight

Jika skor prioritas termasuk MVP, Laravel yang menghitungnya menggunakan aturan eksplisit dan versi aturan. Faktor yang boleh digunakan harus benar-benar tersedia, misalnya tingkat bahaya yang ditinjau petugas, umur laporan, dampak fasilitas penting yang tervalidasi, dan jumlah laporan terkait. Output AI hanyalah rekomendasi sampai dikonfirmasi. Simpan `score`, band, breakdown faktor, dan `rules_version`; jangan membuat skor berbasis data yang tidak tersedia.

## 13. Keamanan, Privasi, dan Retensi

- HTTPS pada deployment; API key Langflow hanya di server/secret manager.
- Rate limit login, upload submission, endpoint review, dan feedback.
- Batasi jumlah/ukuran gambar; verifikasi MIME dari konten, bukan hanya ekstensi.
- Foto quarantine tidak boleh dapat diakses publik. URL bertanda tangan harus singkat masa berlakunya dan tidak masuk log.
- Kirim ke AI hanya data minimum yang diperlukan. Jangan kirim nama, email, nomor telepon, auth token, atau alamat lengkap kecuali benar-benar diperlukan dan telah ditinjau.
- Moderasi AI dapat salah. Hasil ambigu diarahkan ke manusia; sediakan alasan keputusan dan mekanisme review.
- Jangan menyimpan konten sensitif dalam log atau pesan error. Tampilkan alasan penolakan secara umum.
- Hapus foto temporary setelah penolakan/kedaluwarsa sesuai TTL; retensi, penghapusan, dan audit harus dapat diuji.
- Idempotency key untuk mencegah retry upload menciptakan beberapa submission/record tidak sengaja.
- Perubahan status dan keputusan human review menyimpan actor, waktu, dan alasan.

## 14. Observability dan Operasional

Log minimum: request/correlation ID, submission ID, job/run ID, tipe flow, durasi, attempt, status, error code, dan flow version. Jangan log token, isi gambar, signed URL, payload PII, atau raw prompt jika tidak diperlukan.

Sediakan metrik sederhana atau log terstruktur untuk:
- submission per status;
- persentase moderasi manual;
- error/timeout per flow;
- retry dan failed jobs;
- jumlah kemungkinan duplikat dan hasil review;
- lama proses dari submit sampai keputusan.

Worker queue dan scheduler untuk kedaluwarsa file temporary harus dicantumkan dalam README/setup lokal.

## 15. Acceptance Criteria

MVP backend dinyatakan memenuhi PRD jika:

1. Submission dapat dibuat dengan foto, deskripsi, waktu, dan koordinat; server merespons `202` dan ID submission.
2. Foto berada di private temporary storage saat pemeriksaan; tidak dapat diakses publik.
3. File invalid, terlalu besar, atau tipe tidak diizinkan ditolak oleh validasi server.
4. Flow A yang valid menghasilkan keputusan terstruktur; output invalid atau timeout tidak otomatis meloloskan/menolak laporan.
5. Submission yang ditolak tidak membuat record pada tabel `reports`; foto temporary dijadwalkan dihapus sesuai kebijakan.
6. Kandidat spasial diurutkan berdasarkan jarak aktual dan hasil query dites pada MySQL yang digunakan.
7. Flow B hanya mendapat candidate list yang sudah disaring backend; output kandidat di luar daftar ditolak.
8. Submission yang mungkin duplikat masuk review dan tidak membuat report baru secara otomatis.
9. Hanya submission yang disetujui kebijakan yang berubah menjadi record resmi `reports` dan memindahkan attachment ke storage permanen.
10. Retry job tidak membuat report resmi ganda; gunakan transaksi dan idempotency/unique constraint.
11. Warga tidak bisa melihat data submission/laporan pengguna lain; petugas terikat policy/role.
12. Transisi status resmi selalu dicatat pada status history.
13. Queue retry, timeout, kegagalan flow, cleanup file temporary, dan hasil malformed diuji.
14. `php artisan migrate` berjalan di database pengujian; `php artisan test` berjalan atau kegagalannya dilaporkan jelas.
15. `.env.example`, dokumentasi endpoint, setup MySQL, worker queue, dan konfigurasi Langflow tersedia.

## 16. Urutan Implementasi

1. Audit repository, versi runtime, auth, DB, queue, storage, dan test.
2. Definisikan migration `report_submissions`, `reports`, attachments, moderation results, similarity matches, analysis runs, status histories, serta kategori/relasi yang belum ada.
3. Implementasikan submission API, upload validation, private temporary storage, idempotency, dan polling status.
4. Buat state machine submission dan service finalize/reject/manual review.
5. Buat `LangflowClient` beserta kontrak schema; gunakan mock untuk test.
6. Implementasikan Flow A job dan validasi hasil; tambahkan jalur manual review dan cleanup file.
7. Implementasikan pencarian kandidat MySQL yang terukur dan diuji.
8. Implementasikan Flow B job, validasi candidate IDs, kemungkinan duplikat, dan proses tinjauan.
9. Lengkapi API petugas, laporan resmi, attachment, status history, feedback dan prioritas.
10. Tambahkan policies, rate limits, audit log, dokumentasi API, dan automated tests.
11. Jalankan test, migration pada DB test, dan smoke test integrasi bila Langflow tersedia.

## 17. Definition of Done

Backend siap diintegrasikan ke Flutter dan dashboard bila: migration berjalan pada MySQL; submission dan report resmi memiliki lifecycle terpisah; foto privat saat pemeriksaan; dua adapter Flow Langflow tersedia dan mengikuti schema yang dites; pencarian kandidat spasial berfungsi; duplikat ambigu membutuhkan review; semua keputusan penting memiliki audit trail; endpoint dilindungi; file sementara memiliki cleanup; automated tests dan keterbatasan integrasi dilaporkan secara jujur.

## Referensi Teknis

- Laravel: https://laravel.com/docs
- Laravel Sanctum: https://laravel.com/docs/sanctum
- Laravel Queues: https://laravel.com/docs/queues
- Laravel Filesystem: https://laravel.com/docs/filesystem
- Langflow API: https://docs.langflow.org/
