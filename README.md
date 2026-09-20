# DigestEdu — Platform Edukasi Kesehatan Pencernaan

Platform pembelajaran berbasis web tentang kesehatan organ pencernaan. Dimana user dapat menonton
video edukasi, lalu mengerjakan **kuis**. Dan terdapat Progres quiz yang sudah dikerjakan.

Fitur utama:

- **Landing page** publik berisi katalog video (thumbnail + gembok; menonton perlu login).
- **Panel admin** — manajemen video, pengguna, dan kuis.
- **Portal pengguna** — menonton video, melanjutkan dari posisi terakhir (resume), dan mengerjakan kuis.
- **Kuis** — soal pilihan ganda dibuat otomatis dari transkrip suara video (grounded ke naskah).
- **Gating kuis** — kuis hanya bisa dikerjakan setelah video ditonton ≥ 90%.

---

## Teknologi

| Lapisan | Teknologi |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Inertia.js + Vue 3 + TypeScript, Vite, Tailwind CSS |
| Database | SQLite (default) — bisa diganti MySQL/PostgreSQL |
| Queue | Database queue (Laravel) |
| Video hosting | DynTube (API + iframe embed) |
| Transkrip (STT) | Python + OpenAI Whisper (lokal) + FFmpeg |
| Generate kuis | Google Gemini API |

---

## Prasyarat (yang perlu di-install)

Pastikan semua ini terinstall sebelum menjalankan project:

| Software | Versi | Keterangan |
|---|---|---|
| **PHP** | ≥ 8.3 | dengan ekstensi umum Laravel: `curl`, `mbstring`, `openssl`, `pdo_sqlite`, `fileinfo` |
| **Composer** | 2.x | package manager PHP |
| **Node.js** | ≥ 20 | untuk Vite / build frontend |
| **npm** | ≥ 10 | ikut dengan Node.js |
| **Python** | ≥ 3.10 | untuk transkrip Whisper |
| **FFmpeg** | terbaru | ekstraksi audio; **binary Windows sudah disertakan** di `storage/app/bin/ffmpeg.exe` |
| **Git** | — | untuk clone repo |

**Python — library yang dibutuhkan (untuk fitur transkrip/kuis AI):**

```bash
pip install -U openai-whisper
```

> `openai-whisper` akan menarik **PyTorch** secara otomatis. Model Whisper (`small`, ~460 MB)
> akan otomatis ter-download saat pertama kali transkrip dijalankan.

**Akun/kredensial eksternal:**

- **DynTube** — API key untuk upload & streaming video. Daftar di [dyntube.com](https://www.dyntube.com/).
- **Google Gemini** — API key gratis dari [Google AI Studio](https://aistudio.google.com/apikey).

---

## Langkah Setup (untuk developer yang clone project)

### 1. Clone repository

```bash
git clone https://github.com/<username>/digest-edu.git
cd digest-edu
```

### 2. Install dependency PHP

```bash
composer install
```

### 3. Install dependency JavaScript

```bash
npm install
```

### 4. Install dependency Python (untuk transkrip)

```bash
pip install -U openai-whisper
```

### 5. Setup environment (`.env`)

Salin file contoh, lalu isi kredensial:

```bash
copy .env.example .env        # Windows
# cp .env.example .env        # macOS/Linux
```

Generate application key:

```bash
php artisan key:generate
```

Edit `.env` dan isi bagian berikut:

```env
# Database — default SQLite (paling mudah, tanpa server DB terpisah)
DB_CONNECTION=sqlite

# Queue harus 'database' agar job transkrip & kuis berjalan di background
QUEUE_CONNECTION=database

# DynTube (video hosting)
DYNTUBE_API_KEY=isi_api_key_dyntube_kamu
DYNTUBE_API_URL=https://api.dyntube.com/v1
DYNTUBE_UPLOAD_URL=https://upload.dyntube.com/v1/videos

# Google Gemini (generate kuis AI)
GEMINI_API_KEY=isi_api_key_gemini_kamu
# Model bisa diganti sewaktu-waktu tanpa ubah kode. Gunakan alias 'latest'
# agar tidak terdampak model yang di-retire Google.
GEMINI_MODEL=gemini-flash-lite-latest
```

### 6. Siapkan database (SQLite)

Buat file database kosong lalu jalankan migrasi + seeder:

```bash
# Windows
type nul > database\database.sqlite
# macOS/Linux: touch database/database.sqlite

php artisan migrate --seed
```

> Seeder membuat data awal (kategori, akun admin/pengguna contoh). Cek `database/seeders/` untuk kredensial default.

### 7. (Opsional) Build assets untuk produksi

```bash
npm run build
```

---

## Menjalankan Aplikasi (development)

Aplikasi ini butuh **3 proses berjalan bersamaan**. Buka **3 terminal** terpisah:

**Terminal 1 — web server Laravel:**
```bash
php artisan serve
```
Akses di [http://localhost:8000](http://localhost:8000).

**Terminal 2 — Vite (frontend dev server + hot reload):**
```bash
npm run dev
```

**Terminal 3 — queue worker (WAJIB untuk transkrip & kuis AI):**
```bash
php artisan queue:work --memory=256 --timeout=900 --tries=3
```

> **Penting soal worker:**
> - Transkrip video dan generate kuis berjalan sebagai **background job**. Tanpa worker,
>   naskah akan macet di *"sedang diproses"* dan kuis tidak pernah jadi.
> - `queue:work` **memuat kode ke memori saat start** — setelah mengubah kode job/service,
>   **restart worker** (Ctrl+C lalu jalankan lagi). Saat aktif ngoding, alternatifnya pakai
>   `php artisan queue:listen --timeout=900` yang otomatis memuat ulang kode tiap job.

**Cara ringkas (satu perintah, menjalankan ketiganya sekaligus):**
```bash
composer run dev
```
> Menjalankan `php artisan serve`, `queue:listen`, dan `npm run dev` secara paralel via `concurrently`.

---

## Cara Kerja: Pelacakan Progres Menonton & Gating Kuis

Tujuan: **kuis hanya boleh dikerjakan setelah pengguna benar-benar menonton videonya.**

### Alur pelacakan (tracking) menonton

1. Saat pengguna membuka halaman tonton (`/video/{id}`), frontend (`resources/js/pages/Portal/Watch.vue`)
   langsung mengirim posisi awal ke endpoint `POST /video/{id}/resume`.
2. Selama pengguna berada di halaman, posisi menonton **disimpan setiap 5 detik** ke tabel
   `video_resumes` (kolom `last_position`). Jika player DynTube mengirim `postMessage` berisi
   `currentTime`, nilai itu dipakai; jika tidak, posisi diestimasi bertambah 5 detik tiap 5 detik.
3. Nilai `last_position` inilah yang menjadi dasar **"seberapa jauh video sudah ditonton"**.

### Fitur "Lanjutkan Menonton" (resume)

- Saat halaman dibuka kembali, controller mengirim `resume.last_position` & `resume.progress`
  (persentase) ke frontend. Video dapat dilanjutkan dari posisi terakhir, dan sebuah *progress bar*
  ditampilkan (mis. "60% selesai").

### Validasi terbukanya kuis (gating)

Ambang: **90% dari durasi video** dianggap "sudah menonton". Diterapkan **dua lapis**:

- **Frontend** (`Watch.vue`): jika progres menonton < 90%, tab Kuis menampilkan kunci
  *"Tonton video ini hingga selesai untuk membuka kuis"* beserta progress bar. Kuis **terbuka
  otomatis** begitu progres mencapai 90% (tanpa perlu reload).
- **Backend** (`app/Http/Controllers/Portal/QuizSubmitController.php`): saat submit kuis,
  server mengecek `video_resumes.last_position ≥ 90% durasi`. Jika belum, submit ditolak.
  Lapisan ini mencegah pengguna melewati gate dengan memanggil endpoint secara langsung.

---

## Cara Kerja: Generate Kuis AI dari Naskah Video

Prinsipnya: **soal kuis dibuat HANYA dari apa yang benar-benar diucapkan di video** (grounded ke
transkrip), agar tidak keluar konteks. Prosesnya berjalan otomatis di background saat video diunggah
(jika opsi "generate kuis" dicentang) atau saat admin menekan tombol generate/regenerate.

### Pipeline lengkap

```
Upload video
   │
   ├─► [1] Video di-upload ke DynTube (dapat video_id + iframe link)
   │
   ├─► [2] GenerateTranscriptJob (background):
   │        a. Ambil URL streaming HLS dari DynTube API (hlsUrlWeb)
   │        b. FFmpeg: audio dari HLS  ──►  file MP3 (16kHz mono)
   │        c. Whisper (Python, lokal): MP3  ──►  teks ucapan EXACT (naskah)
   │        d. Simpan naskah ke kolom  videos.transcript
   │
   └─► [3] GenerateQuizJob (background, menunggu naskah siap):
            a. Ambil naskah dari DB (wajib ada — kalau kosong, job menunggu/gagal,
               TIDAK mengarang soal)
            b. Susun prompt yang mengunci AI ke naskah (dilarang menambah
               pengetahuan luar)
            c. Kirim ke Google Gemini  ──►  6 soal pilihan ganda (JSON terstruktur)
            d. Simpan soal ke tabel  questions,  set  quiz_status = 'aktif'
```

### Detail tiap tahap

**1. Transkrip (Speech-to-Text) — `app/Services/TranscriptionService.php`**

- Sumber audio diambil dari **HLS DynTube** (bukan file lokal), lalu **FFmpeg**
  (`storage/app/bin/ffmpeg.exe`) mengekstraknya menjadi MP3.
- MP3 diproses **Whisper** (`scripts/whisper_transcribe.py`) yang berjalan **100% lokal**
  (tanpa API), menghasilkan teks persis seperti yang diucapkan di video.
- Jika video di DynTube belum selesai diproses (HLS belum siap), job otomatis mencoba lagi
  (retry) sampai siap — jadi naskah tidak gagal hanya karena timing.

**2. Generate kuis — `app/Services/GeminiService.php`**

- Prompt menaruh **naskah sebagai satu-satunya sumber fakta**. AI diinstruksikan:
  setiap pertanyaan & jawaban benar HARUS bisa dibuktikan dari naskah; dilarang menambahkan
  istilah/fakta medis yang tidak diucapkan di video.
- Output berupa **JSON terstruktur** (schema tetap): 6 soal, tiap soal 4 opsi (A–D), 1 jawaban benar,
  plus perkiraan timestamp. Posisi jawaban benar diacak.
- **Ketahanan model**: model utama diambil dari `GEMINI_MODEL` di `.env`. Bila model itu sedang
  sibuk (HTTP 503) atau tidak tersedia, sistem otomatis mencoba beberapa model cadangan
  (`gemini-flash-latest`, `gemini-flash-lite-latest`, dll.) sebelum menyerah.
- Jika semua model gagal, sistem memakai *fallback questions* generik agar kuis tetap ada
  (bukan error), dan admin dapat menekan **Regenerate** untuk mencoba lagi.

### Status kuis (`videos.quiz_status`)

| Status | Arti |
|---|---|
| `belum` | Kuis belum diminta untuk video ini |
| `proses` | Sedang dibuat di background (naskah/AI berjalan) |
| `aktif` | Kuis siap dikerjakan |
| `gagal` | Pembuatan gagal (mis. naskah tak tersedia) — bisa di-regenerate |

---

## Perintah yang Sering Dipakai

```bash
# Jalankan test
php artisan test

# Lint & format (Pint untuk PHP, ESLint/Prettier untuk JS)
composer run lint
npm run lint

# Bersihkan cache config setelah mengubah .env
php artisan config:clear

# Memproses ulang antrian job yang gagal
php artisan queue:retry all
```

---

## Struktur Penting

```
app/
├── Http/Controllers/
│   ├── Admin/VideoController.php      # CRUD video + trigger generate kuis
│   └── Portal/
│       ├── VideoWatchController.php   # halaman tonton + data resume/progress
│       └── QuizSubmitController.php   # submit kuis + gating backend
├── Jobs/
│   ├── GenerateTranscriptJob.php      # audio → naskah (background)
│   └── GenerateQuizJob.php            # naskah → kuis AI (background)
└── Services/
    ├── TranscriptionService.php       # FFmpeg + Whisper
    ├── GeminiService.php              # prompt & panggilan Gemini
    └── DyntubeService.php             # integrasi DynTube API
resources/js/pages/
├── Portal/Watch.vue                   # player, tracking nonton, gating & UI kuis
└── Admin/Videos/                      # manajemen video (admin)
scripts/whisper_transcribe.py          # skrip STT Whisper (dipanggil dari PHP)
```

---

## Catatan Keamanan

- **Jangan commit file `.env`** — berisi API key DynTube & Gemini. File ini sudah ada di `.gitignore`.
- Gunakan API key terpisah untuk development dan produksi.
