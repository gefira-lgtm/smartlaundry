# Daftar Akun Demo SmartLaundry

Dokumen ini merupakan catatan internal untuk keperluan presentasi, pengujian, dan demonstrasi sistem SmartLaundry. Akun-akun berikut telah tersedia di database (melalui seeder) dan dapat digunakan untuk masuk melalui formulir login standar (`/login`).

> **Catatan Keamanan**: Demo Role Switcher (bypass login otomatis) telah dihapus dari sistem. Semua akses wajib melalui autentikasi kredensial (email & password) atau simulasi Google OAuth.

---

## Tabel Akun Demo (6 Peran / Roles)

| No | Peran (Role) | Nama Pengguna | Alamat Email | Password | URL Dashboard Tujuan |
|---|---|---|---|---|---|
| 1 | **Owner Platform** (`owner_platform`) | Bambang Sudirgo (Super Admin) | `owner@smartlaundry.id` | `password123` | `/dashboard/platform` |
| 2 | **Admin Owner** (`admin_owner`) | Rina Wijaya (Admin Platform) | `admin.owner@smartlaundry.id` | `password123` | `/dashboard/platform` |
| 3 | **Owner Toko** (`owner_toko`) | Hendra Kurniawan (Owner CleanWash) | `owner.cleanwash@smartlaundry.id` | `password123` | `/dashboard/store` |
| 4 | **Owner Toko** (`owner_toko`) | Dewi Lestari (Owner FreshSoft) | `owner.freshsoft@smartlaundry.id` | `password123` | `/dashboard/store` |
| 5 | **Admin Toko** (`admin_toko`) | Fajar Ramadhan (Admin CleanWash) | `admin.cleanwash@smartlaundry.id` | `password123` | `/dashboard/store` |
| 6 | **Admin Toko** (`admin_toko`) | Maya Anggraini (Admin FreshSoft) | `admin.freshsoft@smartlaundry.id` | `password123` | `/dashboard/store` |
| 7 | **Kurir** (`kurir`) | Budi Santoso (Kurir CleanWash) | `kurir.budi@smartlaundry.id` | `password123` | `/dashboard/courier` |
| 8 | **Kurir** (`kurir`) | Andi Pratama (Kurir FreshSoft) | `kurir.andi@smartlaundry.id` | `password123` | `/dashboard/courier` |
| 9 | **Customer** (`customer`) | Ani Rahmawati | `customer.ani@gmail.com` | `password123` | `/dashboard/customer` |
| 10 | **Customer** (`customer`) | Deni Firmansyah | `customer.deni@gmail.com` | `password123` | `/dashboard/customer` |
| 11 | **Customer** (`customer`) | Siti Nurhaliza | `customer.siti@gmail.com` | `password123` | `/dashboard/customer` |

---

## Petunjuk Penggunaan Saat Demo / Pengujian

1. Buka halaman login di browser: `http://localhost/RPL2/public/login` (atau URL instalasi Anda).
2. Masukkan alamat email dan kata sandi `password123` sesuai peran yang ingin diuji.
3. Klik tombol **Masuk**. Sistem akan memverifikasi password via `password_verify()` dan mengarahkan pengguna secara otomatis ke dashboard sesuai peran masing-masing:
   - Platform Owner & Admin Platform -> `/dashboard/platform`
   - Store Owner & Store Admin -> `/dashboard/store`
   - Kurir -> `/dashboard/courier`
   - Customer -> `/dashboard/customer`
4. Untuk keluar dari sesi akun, klik tombol menu profil di navigasi atas lalu pilih **Keluar** (`/logout`).
