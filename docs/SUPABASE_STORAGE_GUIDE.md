# Panduan Konfigurasi Supabase Storage untuk SlotWaves (FDR Direct Upload)

Dokumen ini menjelaskan langkah-langkah menyiapkan Supabase Object Storage untuk mendukung pengunggahan file berukuran besar (>30 MB hingga 100 MB) pada modul **Flight Daily Report (FDR)** tanpa terbentur batas payload serverless Vercel (4.5 MB).

---

## 1. Pembuatan Bucket di Supabase Dashboard

1. Buka project dashboard Anda di [Supabase Dashboard](https://supabase.com/dashboard).
2. Di menu navigasi samping kiri, pilih **Storage**.
3. Klik tombol **New bucket**.
4. Isi konfigurasi bucket:
   - **Name:** `flight-reports`
   - **Public bucket:** Disarankan aktifkan (ON) atau gunakan RLS Policy publik untuk pembacaan analitik.
   - **File size limit:** Setel minimal `100MB` (atau kosongkan untuk menggunakan batas default project).
   - **Allowed MIME types:** Kosongkan atau masukkan:
     - `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
     - `application/vnd.ms-excel`
     - `text/csv`
     - `application/octet-stream`
5. Klik **Save**.

---

## 2. Pengaturan RLS Policy (Row-Level Security)

Jika bucket diatur ke mode Private, Anda wajib menambahkan Policy agar browser client dapat mengunggah file langsung ke bucket:

### Policy 1: Mengizinkan Unggah (INSERT)
- **Target Roles:** `anon`, `authenticated`
- **Allowed operation:** `INSERT`
- **Policy definition:**
```sql
CREATE POLICY "Allow direct upload to flight-reports"
ON storage.objects FOR INSERT
TO public, anon, authenticated
WITH CHECK (bucket_id = 'flight-reports');
```

### Policy 2: Mengizinkan Download / Streaming Backend (SELECT)
- **Target Roles:** `anon`, `authenticated`
- **Allowed operation:** `SELECT`
- **Policy definition:**
```sql
CREATE POLICY "Allow read flight-reports"
ON storage.objects FOR SELECT
TO public, anon, authenticated
USING (bucket_id = 'flight-reports');
```

---

## 3. Konfigurasi Environment Variables

Tambahkan konfigurasi berikut pada file `.env` lokal dan pada **Vercel Project Settings → Environment Variables**:

```env
# ── Supabase Storage Configuration ──────────────────────────────
SUPABASE_URL=https://[YOUR_PROJECT_REF].supabase.co
SUPABASE_ANON_KEY=[YOUR_SUPABASE_ANON_KEY]
SUPABASE_SERVICE_ROLE_KEY=[YOUR_SUPABASE_SERVICE_ROLE_KEY]

# Bucket Storage untuk Flight Daily Report (FDR)
NEXT_PUBLIC_SUPABASE_STORAGE_BUCKET=flight-reports
SUPABASE_STORAGE_BUCKET=flight-reports
```

> **Catatan:**
> - `NEXT_PUBLIC_SUPABASE_STORAGE_BUCKET` digunakan oleh frontend dropzone (`window.ENV` / `process.env`).
> - `SUPABASE_STORAGE_BUCKET` digunakan sebagai cadangan fallback pada backend Laravel.
> - Default sistem SlotWaves otomatis mengarah ke `flight-reports`.

---

## 4. Penanganan Error Umum

| Kode Error | Penyebab | Solusi |
|---|---|---|
| `404 NoSuchBucket` | Bucket belum dibuat di Supabase Storage atau nama bucket salah | Buat bucket `flight-reports` di dashboard Supabase Storage sesuai panduan di atas. |
| `403 Unauthorized / violates row-level security` | RLS Policy memblokir aksi INSERT client | Tambahkan policy `INSERT` untuk role `anon`/`authenticated` di bucket `flight-reports`. |
| `413 Payload Too Large` | Ukuran file melebihi batas bucket Supabase | Naikkan ukuran file size limit di pengaturan bucket Supabase menjadi 100 MB. |
