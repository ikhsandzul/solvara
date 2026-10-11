# PRD Backend — Solvara (Laravel + MySQL)

**Versi:** 1.0  
**Tanggal:** 11 Oktober 2026  
**Status:** Draft implementasi MVP hackathon  
**Audience:** AI coding agent backend dan developer  
**Komponen:** Laravel REST API, MySQL, queue worker, object/file storage

## Instruksi untuk AI Coding Agent

Bangun backend **Laravel + MySQL saja** sesuai dokumen ini. Flutter, dashboard petugas, dan IBM Langflow merupakan konsumen/layanan terpisah. Sebelum mengubah kode, periksa repository, versi PHP/Laravel, database, package, konvensi existing, serta test yang sudah ada. Laporkan temuan dan rencana singkat; lalu implementasikan secara bertahap tanpa menghapus kode yang masih berguna.

Jangan menanamkan credential Langflow/model ke source code. Jangan membuat data palsu agar integrasi tampak berhasil. Bila endpoint atau response frontend berbeda dari spesifikasi ini, buat kontrak API terpusat dan dokumentasikan perubahan. Jalankan migration, test, dan `php artisan test` bila lingkungan mendukung; jangan mengklaim test berhasil bila belum dijalankan.

## 1. Ringkasan Produk dan Tujuan

Solvara adalah platform civic intelligence untuk mengelola pengaduan fasilitas publik seperti jalan rusak, sampah menumpuk, drainase tersumbat, dan lampu jalan mati. Backend adalah sumber kebenaran (*source of truth*) untuk akun, laporan, kategori, status penanganan, hasil analisis AI, skor prioritas, dan bukti verifikasi.

Tujuan MVP:
- menyediakan REST API aman untuk aplikasi Flutter warga dan dashboard petugas;
- menerima dan menyimpan laporan serta lampiran dengan validasi;
- memproses analisis AI secara asynchronous melalui IBM Langflow;
- menemukan kandidat laporan terkait dengan query backend yang terukur;
- menghitung skor prioritas menggunakan aturan eksplisit yang berversi;
- mencatat setiap perubahan status dan keputusan verifikasi sebagai audit trail.

## 2. Cakupan dan Batasan

**Termasuk:** autentikasi dan role; CRUD laporan sesuai hak akses; kategori; upload foto; riwayat status; feedback warga; hasil analisis AI; daftar dan filter untuk petugas; prioritas; verifikasi atau buka kembali laporan; queue, retry terbatas, logging dan API documentation.

**Tidak termasuk MVP:** pembayaran; integrasi langsung dengan sistem pemerintah; penugasan otomatis ke instansi eksternal; notifikasi push produksi; model training sendiri; pengenalan foto otomatis kecuali model multimodal telah dikonfigurasi dan diuji; keputusan otomatis menerima, menolak, atau memverifikasi laporan.

## 3. Arsitektur dan Stack

- **Backend:** Laravel REST API. Gunakan versi yang sudah ada di repository; jika proyek baru, pilih versi stabil yang kompatibel dengan runtime hosting.
- **Database:** MySQL, dengan migration, foreign key, unique constraint, dan indeks yang sesuai.
- **Authentication:** Laravel Sanctum untuk token/API sesuai pola aplikasi; semua endpoint terlindungi menerapkan authorization berbasis role dan kepemilikan record.
- **Async processing:** Laravel Queue + database queue untuk MVP; worker harus dijalankan di development/demo dan deployment.
- **File storage:** Laravel Storage; gunakan private storage untuk file awal dan URL sementara atau endpoint berotorisasi bila lampiran tidak publik.
- **AI integration:** service class khusus yang memanggil Langflow dari server. Flutter tidak boleh memanggil Langflow langsung.
- **Testing:** PHPUnit/Pest mengikuti pola repository; Laravel HTTP tests untuk authorization dan API contract.

Batas tanggung jawab: Laravel memvalidasi dan menyimpan data, memilih kandidat laporan terkait, menghitung prioritas deterministik, mengelola status, dan memanggil Langflow. Langflow hanya menganalisis payload yang disanitasi dan mengembalikan insight terstruktur.

## 4. Peran dan Akses

| Role | Hak utama |
|---|---|
| `citizen` | Membuat laporan, melihat laporan miliknya, membaca status publik, mengirim feedback pada laporan miliknya. |
| `officer` | Melihat laporan dalam cakupan yang diizinkan, meninjau hasil AI, mengubah status, menambahkan catatan tindak lanjut, dan memverifikasi bukti. |
| `admin` | Mengelola kategori, akun/role sesuai kebijakan, dan konfigurasi operasional yang dibutuhkan. |

Backend wajib menolak akses berdasarkan ID saja. Setiap query detail/list harus menerapkan scope kepemilikan atau cakupan tugas petugas. Catatan internal petugas dan payload AI mentah tidak boleh dibagikan kepada warga.

## 5. Alur Bisnis Inti

### 5.1 Pembuatan laporan
1. Flutter mengirim data dan lampiran melalui `POST /api/v1/reports`.
2. Laravel memvalidasi user, deskripsi, kategori, waktu, koordinat/alamat dan file.
3. Laravel menyimpan laporan dengan status `submitted` serta analisis `queued`.
4. Laravel mengembalikan `201 Created` dengan ID laporan tanpa menunggu AI selesai.
5. Laravel memasukkan `AnalyzeReportJob` ke queue.

### 5.2 Analisis AI
1. Job mengubah analysis status menjadi `processing`.
2. Backend mencari kandidat laporan terkait berdasarkan kategori, waktu, lokasi bila tersedia, dan kemiripan teks sederhana.
3. Backend membentuk payload yang sudah disanitasi dan memanggil flow Langflow.
4. Backend menormalisasi dan memvalidasi response terhadap schema. Jika valid, hasil disimpan di `ai_analyses`; jika gagal, tandai `failed` dan lakukan retry terbatas.
5. Backend menghitung/menyegarkan skor prioritas dari faktor yang tersedia; hasil AI tidak boleh secara langsung mengubah status laporan.

### 5.3 Tindak lanjut dan verifikasi
Petugas meninjau laporan dan insight AI, lalu membuat perubahan status yang valid. Perubahan status ditulis ke `report_status_histories`. Saat petugas mengunggah bukti setelah perbaikan, status menjadi `resolved_pending_verification`. Petugas berwenang melakukan verifikasi atau membuka kembali laporan. AI dapat membantu membandingkan bukti bila dukungan vision tersedia, tetapi keputusan `verified` tetap milik petugas.

## 6. Status dan Aturan Transisi

Status laporan yang disarankan: `submitted`, `needs_review`, `accepted`, `in_progress`, `resolved_pending_verification`, `verified`, `reopened`, `rejected`. Status analisis AI disimpan terpisah: `queued`, `processing`, `completed`, `failed`.

- Pembuatan laporan menetapkan `submitted`; proses AI memakai field status terpisah.
- Perubahan status hanya lewat service/domain method yang memvalidasi transisi dan menulis history di transaksi database.
- `verified` hanya dapat ditetapkan petugas berwenang melalui endpoint verifikasi.
- `rejected` dan `reopened` wajib memiliki alasan.
- Status AI gagal tidak menghapus atau menggagalkan laporan yang sudah tersimpan.

## 7. Skema Database MySQL

Gunakan migration, foreign key, index, timestamp, dan soft delete hanya jika cocok dengan audit kebutuhan. ID publik dapat memakai ULID/UUID sesuai konvensi repository.

| Tabel | Kolom inti dan tanggung jawab |
|---|---|
| `users` | `id`, `name`, `email` unique, `password`, `role`, timestamps. Kata sandi harus di-hash Laravel. |
| `categories` | `id`, `code` unique, `name`, `is_active`, timestamps. Contoh code: `road_damage`, `waste`, `drainage`, `streetlight`, `other`. |
| `reports` | `id`, `public_code` unique, `user_id`, `category_id` nullable, `title`, `description`, `latitude` nullable, `longitude` nullable, `address_text` nullable, `occurred_at`, `status`, timestamps. |
| `report_attachments` | `id`, `report_id`, `path`, `kind` (`initial` / `after_repair`), `mime_type`, `size_bytes`, timestamps. Jangan menyimpan binary file di kolom MySQL. |
| `ai_analyses` | `id`, `report_id`, `flow_version`, `provider`, `model_id`, `category_suggested`, `summary`, `keywords_json`, `urgency_suggestion`, `confidence_band`, `related_report_ids_json`, `pattern_insight`, `recommended_next_step`, `missing_information_json`, `needs_human_review`, `status`, `error_code` nullable, timestamps. Simpan raw response hanya jika perlu dan telah disanitasi. |
| `priority_scores` | `id`, `report_id`, `score`, `band`, `factor_breakdown_json`, `rules_version`, timestamps. Skor harus dapat direproduksi dari faktor yang tercatat. |
| `report_status_histories` | `id`, `report_id`, `actor_id`, `old_status`, `new_status`, `note`, `created_at`. Append-only di level aplikasi. |
| `verification_reviews` | `id`, `report_id`, `reviewer_id`, `decision` (`verified` / `reopen`), `note`, `created_at`. |
| `report_feedback` | `id`, `report_id`, `user_id`, `type`, `message` nullable, timestamps. |

Tambahkan tabel bawaan Sanctum dan queue sesuai penggunaan (`personal_access_tokens`, `jobs`, `failed_jobs`). Buat indeks untuk `reports.user_id`, `reports.status`, `reports.category_id`, `reports.created_at`, dan field geospasial jika benar-benar digunakan. Pastikan foreign key dan unique constraint sesuai kebutuhan.

## 8. REST API Contract

Prefix API: `/api/v1`. Bentuk JSON dapat mengikuti konvensi resource Laravel yang sudah ada, tetapi semua endpoint dan field harus terdokumentasi serta konsisten dengan PRD Flutter.

| Method + endpoint | Akses | Fungsi |
|---|---|---|
| `POST /auth/login` | Publik + rate limit | Login, kembalikan token dan profil. |
| `POST /auth/register` | Opsional sesuai keputusan MVP | Registrasi warga bila diaktifkan. Jangan membuka registrasi petugas/admin. |
| `POST /auth/logout` | Semua user login | Revoke token aktif. |
| `GET /auth/me` | Semua user login | Profil user aktif. |
| `GET /categories` | User login | Kategori aktif. |
| `POST /reports` | Citizen | Buat laporan; multipart form-data. |
| `GET /reports` | Citizen/officer/admin | Daftar dengan scope akses, pagination dan filter. |
| `GET /reports/{id}` | Pemilik atau petugas berwenang | Detail laporan, lampiran yang boleh dilihat, timeline dan insight publik. |
| `POST /reports/{id}/feedback` | Pemilik laporan | Kirim feedback. |
| `GET /officer/dashboard/summary` | Officer/admin | Ringkasan jumlah laporan menurut status/kategori. |
| `GET /officer/reports` | Officer/admin | Daftar laporan dengan filter dan sort yang diizinkan. |
| `PATCH /officer/reports/{id}/status` | Officer/admin sesuai policy | Perubahan status dengan catatan. |
| `POST /officer/reports/{id}/verification` | Officer/admin sesuai policy | Verifikasi atau buka kembali laporan. |

### Request buat laporan

Gunakan `multipart/form-data`:
- `description` (wajib, 20–2000 karakter);
- `title` (opsional, panjang maksimum ditentukan validator);
- `category_id` atau `category_code` (opsional sesuai UI dan aturan kategori);
- `latitude`, `longitude` (keduanya harus disediakan bersama; boleh kosong bila memakai alamat manual);
- `address_text` (opsional tetapi lokasi perlu dijelaskan melalui koordinat atau teks sesuai kebijakan produk);
- `occurred_at` (ISO-8601);
- `attachments[]` (1–3 gambar pada MVP; MIME dan ukuran diverifikasi server).

### Contoh respons sukses

```json
{
  "data": {
    "id": "01JREPORTDEMO",
    "public_code": "SOL-2026-0001",
    "title": "Jalan berlubang dekat sekolah",
    "description": "Jalan berlubang dan sering tergenang saat hujan.",
    "status": "submitted",
    "analysis_status": "queued",
    "created_at": "2026-10-11T10:00:00+07:00"
  }
}
```

Data di atas hanya contoh kontrak, bukan data produksi. Jangan menunggu AI menyelesaikan analisis sebelum memberi response sukses. Flutter dapat menampilkan “Analisis sedang diproses”.

### Bentuk error konsisten

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Periksa kembali data yang dikirim.",
    "fields": {"description": ["Deskripsi wajib diisi."]}
  }
}
```

Gunakan status HTTP yang tepat: `401` belum login, `403` tidak berwenang, `404` tidak ditemukan/tidak boleh dibedakan bila dapat membocorkan data, `422` validasi, `429` rate limit, `5xx` kesalahan server. Jangan kirim stack trace ke client.

## 9. Integrasi IBM Langflow

Buat `LangflowClient` atau service sejenis yang hanya dipakai dari backend/queue. Konfigurasi lewat environment:

- `LANGFLOW_SERVER_URL`
- `LANGFLOW_FLOW_ID`
- `LANGFLOW_API_KEY`
- `LANGFLOW_TIMEOUT_SECONDS`

Gunakan endpoint run yang dibuat pada panel API access Langflow, misalnya `POST /api/v1/run/{FLOW_ID}` untuk API v1. Periksa snippet sesuai versi Langflow terpasang; jangan hardcode flow ID atau API key.

Backend menyediakan context laporan terkait sehingga flow MVP tidak perlu koneksi langsung ke database. Payload ke Langflow hanya memuat deskripsi, kategori yang dipilih, waktu, label lokasi minimal, dan ringkasan singkat laporan kandidat. Jangan kirim nama, email, nomor telepon, token auth, atau foto/binary kecuali ada use case vision yang disengaja dan sudah diuji.

Response Langflow perlu dinormalisasi dari struktur response aktual versi terpasang, lalu divalidasi terhadap field schema. Batasi retries (misalnya maksimal 3 dengan backoff) dan gunakan timeout. Jika AI gagal, laporan tetap valid, analysis status menjadi `failed`, dan petugas dapat tetap memproses laporan.

## 10. Pencarian Laporan Terkait dan Prioritas

### Kandidat laporan terkait

Pada MVP, lakukan pencarian di Laravel menggunakan kategori, rentang waktu, jarak koordinat bila tersedia, dan/atau kemiripan kata sederhana. Batasi jumlah kandidat yang dikirim ke Langflow (misalnya 5–10 kandidat paling relevan). Jangan menyebut kandidat sebagai duplikat pasti sebelum ditinjau.

### Skor prioritas

Skor harus dihitung oleh backend memakai aturan eksplisit dan versi aturan, bukan hasil generasi bebas model. Contoh faktor yang dapat dikonfigurasi: urgensi yang telah ditinjau petugas, dampak pada fasilitas penting yang datanya tersedia, umur laporan, dan jumlah laporan terkait yang tervalidasi. Jika data belum tersedia, jangan mengarang faktor; tandai skor sementara atau `insufficient_data`. `urgency_suggestion` dari AI hanyalah masukan rekomendasi sampai diverifikasi petugas.

## 11. Validasi, Keamanan, dan Privasi

- Validasi seluruh field server-side; lakukan sanitasi output untuk dashboard.
- Foto dibatasi ke format gambar yang diizinkan, ukuran maksimum yang dikonfigurasi, dan nama file acak; jangan percaya ekstensi file dari client.
- Cegah mass assignment dengan `$fillable`/DTO dan Form Request.
- Terapkan policies/gates untuk role, owner scope, dan status transition.
- Rate limit login, pembuatan laporan, feedback, dan endpoint sensitif.
- Gunakan HTTPS di deployment; secret berada di environment/secret manager.
- API key Langflow dan kredensial model tidak pernah dikirim ke Flutter.
- Log ID request, durasi, status, dan kode error; hindari mencatat token, PII berlebihan, prompt sensitif, atau isi lampiran.
- Batasi akses lampiran; jangan menjadikan foto privat publik melalui URL permanen tanpa pertimbangan keamanan.
- Lindungi callback/internal endpoint dengan autentikasi jika kelak digunakan. Untuk MVP, prefer request dari job backend ke Langflow dan tidak membuka endpoint internal tambahan.

## 12. Non-Functional Requirements

- Pembuatan laporan tidak diblokir oleh waktu inferensi AI.
- Semua daftar memakai pagination dan batas ukuran page.
- Job AI idempotent: retry tidak membuat record analisis duplikat yang tidak terkontrol.
- Gunakan transaksi untuk perubahan status dan audit trail.
- Response API stabil, terdokumentasi, dan konsisten dalam timezone ISO-8601.
- Error eksternal Langflow tidak menjatuhkan endpoint submit laporan.

## 13. Acceptance Criteria dan Testing

- Citizen tidak dapat membaca laporan citizen lain dengan menebak ID.
- Officer tidak dapat melakukan aksi di luar policy/cakupan tugas.
- Laporan valid tersimpan dan mengembalikan `201` walau Langflow tidak tersedia.
- Job memanggil Langflow dengan payload tersanitasi dan menyimpan hasil valid.
- Response AI invalid/timeout menghasilkan status analisis gagal dan retry terbatas, bukan menghapus laporan.
- Semua transisi status ilegal ditolak; setiap transisi yang berhasil menulis history.
- Verifikasi memerlukan aktor berwenang dan keputusan eksplisit.
- Skor prioritas menyimpan breakdown faktor dan rules version.
- File upload menolak MIME/size tidak diizinkan.
- Test meliputi auth, authorization, request validation, queue dispatch, Langflow mock, retry/failure, pagination, feedback, status transition, dan verification.

## 14. Urutan Implementasi yang Disarankan

1. Audit repo dan siapkan konfigurasi `.env.example` tanpa secret.
2. Buat migration, model, factory/seed kategori, dan role/policy.
3. Implementasikan auth dan API kategori/laporan dasar.
4. Implementasikan upload lampiran dan pembatasan akses.
5. Implementasikan status history, feedback, dan endpoint petugas.
6. Buat queue/job serta Langflow client memakai mocked response untuk test.
7. Sambungkan flow Langflow nyata, normalisasi schema, retry dan logs.
8. Implementasikan pencarian kandidat laporan dan skor prioritas berversi.
9. Tambahkan API documentation, test, dan instruksi menjalankan queue worker.

## 15. Definition of Done

Backend dianggap siap untuk integrasi MVP jika migration berjalan dari database kosong, endpoint inti terdokumentasi dan diuji, policy mencegah akses lintas user, laporan tersimpan tanpa menunggu AI, analisis Langflow diproses asynchronous, status dan verifikasi memiliki audit trail, secret tidak bocor ke client/log, dan Flutter serta dashboard dapat memakai kontrak API yang sama.

## Referensi Teknis

- Laravel: https://laravel.com/docs
- Laravel Sanctum: https://laravel.com/docs/sanctum
- Laravel Queues: https://laravel.com/docs/queues
- Langflow flow run API: https://docs.langflow.org/api-flows-run
