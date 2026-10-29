# SlotWaves ✈️

### Airport Operational Slot, OASYS DAU & Flight Daily Report (FDR) Intelligence Platform

[![Live Demo](https://img.shields.io/badge/demo-slot--waves.vercel.app-0070f3?style=for-the-badge&logo=vercel)](https://slot-waves.vercel.app)
[![Laravel](https://img.shields.io/badge/Laravel_12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)
[![Supabase](https://img.shields.io/badge/Supabase-3ECF8E?style=for-the-badge&logo=supabase&logoColor=white)](https://supabase.com)
[![Vercel Serverless](https://img.shields.io/badge/Vercel-Serverless-black?style=for-the-badge&logo=vercel)](https://vercel.com)

**SlotWaves** adalah platform intelijen operasional penerbangan berbasis Laravel yang dirancang untuk mengintegrasikan data operasional bandara, jadwal pergerakan pesawat (*Airport Slot Schedule*), laporan analitik **OASYS DAU (Daily Airport Utilization)** (`DAU-01` s/d `DAU-12`), **Flight Daily Report (FDR)** dengan peta jaringan rute interaktif (**InJourney Airports Interactive Route Network**), serta **Master Data Referensi Nasional** (**602 Bandara Ditjen Hubud & InJourney Airports** + **56 Maskapai**) ke dalam satu dashboard analitik terpadu dan laporan siap ekspor.

🔗 **Live Production Demo:** [https://slot-waves.vercel.app](https://slot-waves.vercel.app)

---

## 🌟 Fitur & Modul Utama

### 1. Airport Operational Slot Management (`Airport Slot Schedule`)
* **Visualisasi Timeline 24 Jam**: Memetakan slot kedatangan (*Arrival*) dan keberangkatan (*Departure*) secara granular sepanjang 24 jam operasional (mendukung tampilan zona waktu lokal WIB/WITA/WIT maupun UTC).
* **Stand & Apron Occupancy (OPC & RON)**: Pelacakan utilisasi *parking stand* pesawat berdasarkan tipe armada, *Remain Over Night* (RON), *Occupancy Parking Stand* (OPC), dan durasi *ground turnaround*.
* **Runway & Slot Capacity Analytics**: Evaluasi otomatis kapasitas bandara (*Normalized Aircraft Capacity* / NAC) serta status independen *Arrival Capacity* dan *Departure Capacity* (`AVAILABLE`, `MAX`, `OVER CAPACITY`) untuk mendeteksi potensi *bottleneck* pada jam sibuk (*peak hours*).
* **Automated PDF Schedule Ingestion**: Ekstraksi otomatis jadwal penerbangan, nomor penerbangan, tipe pesawat, hari operasi (*DoS*), dan rute langsung dari dokumen PDF jadwal slot bandara (`app/Services/PdfParser.php` & `parse_pdf.php`).

### 2. OASYS DAU Analytics Suite (`DAU-01` s/d `DAU-12`)
* **Multi-Report Ingestion (`DAU-01` s/d `DAU-12`)**: Pipeline parsing dan validasi template otomatis untuk laporan tabular Excel standar OASYS:
  * `DAU-01` (Arus Lalu Lintas) & `DAU-02` (Total & Komparasi Historis)
  * `DAU-03` (Status Penerbangan)
  * `DAU-04`, `DAU-04A`, `DAU-04B` (Rute Asal / Tujuan & Operator)
  * `DAU-05`, `DAU-05A`, `DAU-05B`, `DAU-05C` (Pangsa Pasar & Performa Maskapai / Operator)
  * `DAU-06` (Distribusi Tipe Pesawat)
  * `DAU-10`, `DAU-10A`, `DAU-10B` (Jam Puncak Pesawat, Penumpang, Terminal, & Block On/Off)
  * `DAU-11` & `DAU-12` (Statistik Pergerakan ARR/DEP & DOM/INT)
* **Historical Trend & Multi-Period Comparison**: Komparasi lintas periode laporan DAU dengan sinkronisasi warna grafik yang konsisten untuk kebutuhan pelaporan eksekutif.
* **Audit & Data Integrity**: Validasi struktur template otomatis untuk memastikan *single source of truth* pada metrik pergerakan pesawat, penumpang, kargo, dan bagasi.
* **Export-Ready**: Mendukung ekspor laporan analitik dan komparasi ke format **PDF**, **Excel**, dan **CSV**.

### 3. Flight Daily Report (FDR) Intelligence & Interactive Route Network GIS
* **Large-File Streaming Parser (`FdrStreamingParser`)**: Pipeline pemrosesan *chunked* & *memory-safe* untuk file laporan harian FDR berukuran besar (hingga `>40 MB` `.xls` / `.xlsx` / `.csv` / `.xml`) dengan pengalihan otomatis langsung ke dashboard analitik setelah proses unggah selesai.
* **InJourney Airports Interactive Route Network**: Peta GIS interaktif berbasis **Leaflet + OpenStreetMap** dengan kurva lintasan Bezier geografis (`[lat, lng]`), penanda arah terbang (*directional arrowheads*), dan *endpoint badge* bandara yang tersinkronisasi penuh saat *pan* maupun *zoom*.
* **Comprehensive Operational KPI**: Analisis *On-Time Performance* (OTP), distribusi keterlambatan (*delay*), *load factor* penumpang, *aircraft utilization*, serta rincian trafik domestik dan internasional.

### 4. Master Data Referensi Nasional (InJourney Airports & Ditjen Hubud)
* **602 Bandara Terverifikasi**: Mencakup **37 Bandara PT. Angkasa Pura Indonesia (InJourney Airports)** yang dikunci sebagai referensi utama (*Region 1–6*), serta **565 Bandara Ditjen Hubud Kemenhub** (UPT Hubud, UPTD Pemda, BUMN, Swasta, TNI, Missionaris) dan bandara hub internasional.
* **56 Maskapai Penerbangan**: Data referensi maskapai berjadwal domestik (AOC 121), internasional, kargo, dan *charter* lengkap dengan kode IATA, kode organisasi, dan status operasional.

### 5. Serverless & Cloud-Ready Hybrid Architecture
* **Vercel Serverless Optimization**: Dikonfigurasi melalui `vercel.json` dan entrypoint `api/index.php` (`vercel-php@0.8.0`, `1024 MB` memory) untuk eksekusi cepat di lingkungan *stateless*.
* **SQLite Fallback & Instant Auto-Hydration (`FdrDatabaseService`)**: Mekanisme *fail-safe* otomatis yang melewati kendala *TCP connection pooler* (SNI/port `6543`) pada environment serverless dengan mengalihkan query ke SQLite lokal (`/tmp/storage/database.sqlite`) dan melakukan *auto-hydration* **602 Bandara** serta **56 Maskapai** dalam waktu `< 10ms`.
* **Supabase PostgREST & Storage Integration**: Persistensi metadata laporan, sinkronisasi baris penerbangan, dan penyimpanan dokumen sumber terkompresi melalui API HTTPS Supabase PostgREST (`/rest/v1/*`) dan Supabase Storage.

---

## 🛠️ Tech Stack

| Layer | Komponen / Teknologi |
| :--- | :--- |
| **Backend Framework** | [Laravel 12](https://laravel.com) (PHP 8.2+) |
| **Frontend & UI** | Laravel Blade, [Tailwind CSS](https://tailwindcss.com), [Vite](https://vitejs.dev), Chart.js, Leaflet.js (OpenStreetMap GIS) |
| **Database & Persistence** | Hybrid Engine: SQLite (dengan *Serverless Auto-Hydration*) + PostgreSQL / [Supabase](https://supabase.com) PostgREST API |
| **Data Ingestion & Export** | Custom Streaming XLS/XML/CSV Parser, PDF Schedule Parser (`smalot/pdfparser`), PhpSpreadsheet, DomPDF (`barryvdh/laravel-dompdf`) |
| **Deployment & Hosting** | [Vercel Serverless](https://vercel.com) (`api/index.php`, `vercel.json`) |

---

## 📁 Struktur Direktori

```text
SlotWaves/
├── api/
│   └── index.php                           # Serverless entrypoint & runtime bootstrap untuk Vercel
├── app/
│   ├── Console/Commands/                   # CLI commands (sync Hubud, import bandara, cek koneksi DB)
│   ├── Http/Controllers/                   # Controller utama (Upload, Schedule, DAU, FDR, MasterData, API)
│   ├── Models/                             # Eloquent models (Upload, Flight, FdrFlight, Airport, Airline)
│   └── Services/
│       ├── Dau/                            # Registry, validator, & parser spesifik DAU-01 s/d DAU-12
│       ├── FlightDailyReport/              # FdrStreamingParser, FdrDatabaseService, Analytics, & PDF Export
│       ├── Storage/                        # Integrasi Supabase Storage Service
│       └── ...                             # PdfParser, CapacityService, TimelineEngine, PairingService
├── database/
│   ├── migrations/                         # Skema tabel bandara, maskapai, jadwal slot, DAU, & FDR
│   ├── seeders/                            # Seeder Master Data (HubudAirportSeeder, HubudAirlineSeeder)
│   └── database.sqlite                     # Pre-seeded SQLite bundle (602 Bandara & 56 Maskapai)
├── resources/
│   ├── views/
│   │   ├── dau/                            # Blade views & partial analitik DAU-01 s/d DAU-12
│   │   ├── fdr/                            # Dashboard FDR & peta GIS Interactive Route Network
│   │   ├── master-data/                    # Direktori tampilan Master Data Bandara & Maskapai
│   │   ├── schedule/                       # Dashboard kapasitas & jadwal slot bandara
│   │   └── timeline.blade.php              # Visualisasi timeline operasional 24 jam
│   └── css / js                            # Asset styling Tailwind CSS & skrip interaktif
├── routes/
│   └── web.php                             # Definisi routing web, dashboard, & REST API
├── scripts/                                # Skrip utilitas pendukung & generator data sampel
├── hubud_bandara_map.json                  # Dataset cache master bandara Ditjen Hubud Kemenhub
├── parse_pdf.php                           # Skrip CLI pengujian ekstraksi jadwal PDF
├── seed_all_daus.php                       # Skrip CLI seeding & verifikasi laporan DAU
└── vercel.json                             # Konfigurasi routing, memory (1024 MB), & runtime Vercel
```

---

## 🚀 Panduan Instalasi Lokal

### Prasyarat
* **PHP >= 8.2** dengan ekstensi `pdo`, `pdo_sqlite`, `pdo_pgsql`, `mbstring`, `openssl`, `curl`, `zip`, `gd`
* **Composer** v2+
* **Node.js** (v18+) & **NPM**

### Langkah-langkah Instalasi

1. **Clone Repositori**
   ```bash
   git clone https://github.com/ValhallaDemon356/SlotWaves.git
   cd SlotWaves
   ```

2. **Instal Dependensi Backend & Frontend**
   ```bash
   composer install
   npm install
   ```

3. **Konfigurasi Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   > **Catatan Database:** Secara default untuk pengembangan lokal yang cepat, Anda dapat mengatur `DB_CONNECTION=sqlite` pada berkas `.env` (menggunakan `database/database.sqlite` yang sudah terisi data master), atau mengonfigurasi kredensial `pgsql` / `SUPABASE_URL` dan `SUPABASE_SERVICE_ROLE_KEY`.

4. **Setup Database, Migrasi & Sinkronisasi Master Data**
   ```bash
   # Jalankan migrasi skema database
   php artisan migrate

   # Sinkronisasi penuh Master Data (37 Bandara InJourney + 565 Bandara Hubud + 56 Maskapai)
   php artisan slotwaves:sync-hubud --force
   ```

5. **Perintah CLI & Skrip Helper (Opsional)**
   ```bash
   # Memvalidasi integritas data master bandara
   php artisan airports:validate

   # Memeriksa status koneksi database & fallback
   php artisan db:check-connection

   # Melakukan seeding sampel seluruh tipe laporan DAU
   php seed_all_daus.php

   # Menguji ekstraksi jadwal penerbangan dari dokumen PDF
   php parse_pdf.php
   ```

6. **Jalankan Server Pengembangan**
   ```bash
   # Terminal 1: Jalankan server Laravel
   php artisan serve

   # Terminal 2: Jalankan Vite asset bundler ( atau `npm run build` untuk produksi )
   npm run dev
   ```
   Akses aplikasi melalui browser di **`http://127.0.0.1:8000`**.

---

## ☁️ Deployment ke Vercel (Serverless)

Repositori ini telah dikonfigurasi secara penuh untuk deployment *serverless* di **Vercel** melalui [`vercel.json`](vercel.json) dan [`api/index.php`](api/index.php):

1. Hubungkan repositori GitHub `ValhallaDemon356/SlotWaves` ke dashboard proyek Vercel Anda.
2. Tambahkan **Environment Variables** berikut pada pengaturan proyek Vercel:
   * `APP_KEY` (gunakan nilai `base64:...` dari `php artisan key:generate --show`)
   * `APP_ENV=production`
   * `APP_DEBUG=false`
   * `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY`, dan `SUPABASE_BUCKET` (untuk sinkronisasi cloud & penyimpanan objek)
3. Saat deployment berjalan, Vercel otomatis mengeksekusi `npm ci && npm run build` serta menjalankan runtime PHP serverless pada `api/index.php`.
4. Pada saat runtime, [`FdrDatabaseService::ensureWorkingConnection()`](app/Services/FlightDailyReport/FdrDatabaseService.php) secara otomatis menginisialisasi SQLite *serverless* di `/tmp/storage/database.sqlite`, meng-hidrasi seluruh **602 Bandara** dan **56 Maskapai** dari bundel [`database/database.sqlite`](database/database.sqlite), serta menyinkronkan data laporan FDR/DAU ke Supabase melalui HTTPS PostgREST.

---

## 📄 Lisensi & Kontribusi

Proyek **SlotWaves** dikembangkan untuk kebutuhan manajemen slot bandara, evaluasi kapasitas operasional, dan intelijen data penerbangan (**PT. Angkasa Pura Indonesia / InJourney Airports** & **Ditjen Hubud**). Silakan buat *Issue* atau ajukan *Pull Request* untuk kontribusi maupun pengembangan fitur lebih lanjut.
