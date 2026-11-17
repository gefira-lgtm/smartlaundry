# CHECKLIST PENGUMPULAN TUGAS BESAR UAS
## REKAYASA PERANGKAT LUNAK (RPL) — SMARTLAUNDRY PRO
**Tahun Akademik:** 2026/2027  
**Tanggal Evaluasi:** 17 November 2026  
**Status Evaluasi:** Siap Pengumpulan (*Submission Ready*)

Dokumen ini merupakan instrumen verifikasi kelengkapan seluruh berkas akademis dan teknis sebelum penyerahan tugas akhir ke dosen pengampu dan pengunggahan ke repositori GitHub.

---

### TABEL VERIFIKASI KELENGKAPAN UAS

| No | Komponen Evaluasi | Kriteria Pemeriksaan | Status Verifikasi | Catatan & Lokasi File |
|:--:|:---|:---|:---:|:---|
| 1 | **Repositori GitHub** | Repositori bersih, struktur file rapi, tanpa file sampah | `[SELESAI]` | Repositori lokal `c:\xampp\htdocs\RPL2` siap di-*push* ke remote GitHub |
| 2 | **README.md Profesional** | 14 Bab standar GitHub, badges, instalasi, demo akun | `[SELESAI]` | [README.md](file:///c:/xampp/htdocs/RPL2/README.md) (25.6 KB, lengkap) |
| 3 | **Identitas Mahasiswa** | Nama, NIM, Kelompok, Tema/Topik, Mata Kuliah, Dosen | `[PERLU VERIFIKASI]` | Placeholder tersedia di cover PRD, SDD, Pengujian, dan README; perlu diisi nama & NIM anggota tim |
| 4 | **Deskripsi Aplikasi** | Latar belakang, rumusan masalah, solusi marketplace AI | `[SELESAI]` | Konsisten di seluruh dokumen dan README.md |
| 5 | **Fitur AI (SmartBot)** | Multi-agent, Groq/Gemini, fallback NLU, anti-IDOR, jam WIB | `[SELESAI]` | Tervalidasi 47 intent test, salam waktu WIB di `AIChatbotService.php` |
| 6 | **Link Video YouTube** | Video demonstrasi pemesanan, pembayaran & chatbot | `[BELUM]` | Placeholder `[Link Video Demonstrasi YouTube]` tersedia; isi tautan setelah rekaman selesai |
| 7 | **Panduan Instalasi** | Prasyarat, clone, import SQL/migrate CLI, .env, akun demo | `[SELESAI]` | Terdokumentasi langkah demi langkah di Bab 8 README.md |
| 8 | **Product Requirements Document (PRD)** | 11 Bab akademik, FR-001 s/d FR-SET-001, NFR, User Stories | `[SELESAI]` | [docs/PRD-SmartLaundry-Pro.md](file:///c:/xampp/htdocs/RPL2/docs/PRD-SmartLaundry-Pro.md) (52 KB) |
| 9 | **Software Design Document (SDD)** | 12 Bab arsitektur MVC, ERD 28 tabel, Use Case, Sequence | `[SELESAI]` | [docs/SDD-SmartLaundry-Pro.md](file:///c:/xampp/htdocs/RPL2/docs/SDD-SmartLaundry-Pro.md) (52 KB) |
| 10 | **Laporan Pengujian Sistem** | Black Box, Unit, Security anti-IDOR, 497 test matrix | `[SELESAI]` | [docs/LAPORAN-PENGUJIAN-SmartLaundry-Pro.md](file:///c:/xampp/htdocs/RPL2/docs/LAPORAN-PENGUJIAN-SmartLaundry-Pro.md) (31.2 KB, 99.8% Pass) |
| 11 | **Dokumen Gabungan UAS (Master)** | Cover, Kata Pengantar, Daftar Isi, PRD, SDD, Pengujian | `[SELESAI]` | [docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.md](file:///c:/xampp/htdocs/RPL2/docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.md) (160 KB) |
| 12 | **Dokumen Gabungan PDF / Siap Cetak** | Format A4, margin rapi, penomoran halaman, tabel rapi | `[SELESAI]*` | File HTML siap cetak: [docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.html](file:///c:/xampp/htdocs/RPL2/docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.html) (Cetak ke PDF via browser `Ctrl + P`) |
| 13 | **Screenshot Aplikasi** | Tangkapan layar antarmuka dashboard dan chatbot | `[SELESAI]` | Folder `docs/screenshots/` memuat `05_dashboard_admin_toko.jpg` dan `07_chatbot_ai.jpg` |
| 14 | **Perlindungan Kredensial Rahasia** | Tidak ada API Key, password, token, atau data pribadi | `[SELESAI]` | `.gitignore` aktif memblokir `.env`, `.env.example` bersih tanpa rahasia |
| 15 | **Kesiapan Eksekusi Source Code** | Source code dapat dijalankan di Apache XAMPP & PHP 8.2 | `[SELESAI]` | Arsitektur MVC Native berjalan normal, migrasi database sukses |

*\*Catatan Item 12: Karena sub-shell sandbox terminal membatasi pemanggilan browser headless tanpa GUI, dokumen gabungan telah di-render menjadi HTML format akademik A4 lengkap dengan tombol cetak instan ke PDF.*

---

### RINGKASAN TINDAKAN MAHASISWA SEBELUM PENGUMPULAN:

1. **Lengkapi Identitas Tim:**
   Buka file [docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.md](file:///c:/xampp/htdocs/RPL2/docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.md) dan [README.md](file:///c:/xampp/htdocs/RPL2/README.md), lalu ganti placeholder berikut:
   - `[Nama Mahasiswa 1]` $\rightarrow$ Nama Anda / Ketua
   - `[NIM Mahasiswa 1]` $\rightarrow$ NIM Anda
   - `[Nama Dosen Pengampu]` $\rightarrow$ Nama Dosen RPL Anda
   - `[Nama Universitas]` $\rightarrow$ Nama Kampus Anda

2. **Cetak PDF Dokumen Gabungan:**
   - Buka file [docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.html](file:///c:/xampp/htdocs/RPL2/docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.html) di Google Chrome atau Microsoft Edge.
   - Klik tombol **🖨️ Cetak ke PDF (Ctrl + P)** di pojok kanan atas.
   - Pilih Tujuan: **Save as PDF**, Ukuran Kertas: **A4**, Centang: **Background graphics**.
   - Simpan dengan nama: `docs/DOKUMENTASI-LENGKAP-UAS-RPL-SmartLaundry-Pro.pdf`.

3. **Rekam Video Demonstrasi (Opsional / Syarat UAS):**
   - Rekam demo singkat alur: Login $\rightarrow$ Pesan Cucian $\rightarrow$ Bayar QRIS $\rightarrow$ Tracking Kurir $\rightarrow$ Tanya Jawab SmartBot AI.
   - Upload video ke YouTube (Unlisted/Public) dan tempel tautannya pada `README.md` menggantikan `[Link Video Demonstrasi YouTube]`.

4. **Push ke GitHub Pribadi / Kelompok:**
   Jalankan perintah berikut di Git Bash / Terminal Anda saat siap mempublikasikan:
   ```bash
   git add .
   git commit -m "docs: complete UAS documentation package, PRD, SDD, testing report, and README"
   git push origin main
   ```
