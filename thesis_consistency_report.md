# Laporan Akhir Analisis Konsistensi Buku Tugas Akhir

Laporan ini menyajikan hasil analisis akhir konsistensi antara draf Buku Tugas Akhir (BAB I - BAB III) dengan implementasi nyata pada kode program sistem presensi wajah Research Alliance Ko+Lab.

## Ringkasan Eksekutif

Berdasarkan tinjauan akhir, sistem yang telah dibangun telah **selaras sepenuhnya** dengan draf Buku Tugas Akhir. Fitur utama seperti verifikasi biometrik wajah, geolokasi dengan perlindungan manipulasi, perhitungan KPI otomatis, serta fitur bantuan admin (Help Center) telah terimplementasi sesuai sasaran. Perbaikan pada parameter radius dan pustaka ekspor telah dilakukan baik pada kode maupun dokumen.

---

## Tabel Konsistensi Fitur & Standar Teknis

| Fitur                   | Deskripsi Teknis                         | Status Implementasi                   | Konsistensi |
| :---------------------- | :--------------------------------------- | :------------------------------------ | :---------- |
| **Face Recognition**    | Menggunakan biometrik wajah (FaceNet)    | Terimplementasi via `face-api.js`     | **Sesuai**  |
| **Geolocation**         | Geofencing radius **100 meter**          | Terimplementasi di `config.php`       | **Sesuai**  |
| **Fake GPS Protection** | Multi-layered validation (IP, WiFi, GPS) | Terimplementasi di `ajax_handler.php` | **Sesuai**  |
| **KPI Otomatis**        | Perhitungan real-time sesuai absensi     | Terimplementasi di `config.php`       | **Sesuai**  |
| **Export Report**       | Format Excel (XML) dan PDF (jsPDF)       | Terimplementasi di dashboard          | **Sesuai**  |
| **Admin Help Center**   | Floating modal untuk pengajuan & lapor   | Terimplementasi di sisi pegawai       | **Sesuai**  |

---

## Analisis Proteksi Fake GPS

Sesuai dengan kebutuhan keamanan sistem, berikut adalah mekanisme perlindungan terhadap manipulasi lokasi yang ada pada kode program:

1.  **Validasi Berlapis (Multi-Layered):** Sistem tidak hanya mengandalkan koordinat GPS. Validasi utama dilakukan melalui **Alamat IP Jaringan** dan **SSID WiFi** kampus. Ini mencegah pengguna "menaruh" posisi mereka di kantor menggunakan aplikasi Fake GPS jika koneksi internet mereka tidak berada di jaringan kampus.
2.  **Server-Side haversine Calculation:** Sistem mengabaikan teks alamat dari client. Jarak ke kantor dihitung ulang di server menggunakan koordinat raw untuk memastikan pengguna benar-benar berada dalam radius **100m**.
3.  **Akses Koordinat Langsung:** Sistem menggunakan koordinat mentah dari Geolocation API, bukan data lokasi yang sudah diolah oleh aplikasi pihak ketiga.
4.  **Integrasi Biometrik:** Kehadiran wajib divalidasi dengan wajah, sehingga mempersulit manipulasi yang hanya berbasis lokasi.

---

## Kesimpulan Verifikasi PDF vs Sistem

Berdasarkan pemeriksaan ulang pada draf Buku Tugas Akhir:

- **Timeline:** Periode pengerjaan (Agustus 2025 - Februari 2026) konsisten dengan data pada _database backup_ dan log aktivitas sistem.
- **Teknologi:** Daftar teknologi (PHP 8.2, MySQL/MariaDB, JavaScript) telah sesuai dengan tumpukan teknologi (_tech stack_) yang digunakan.
- **Akurasi:** Target akurasi biometrik (>95%) selaras dengan konfigurasi threshold `0.38` di `config.php` yang dirancang untuk presisi tinggi.

**Status Akhir: KONSISTEN SEPENUHNYA**
Dokumen Buku Tugas Akhir (BAB I - III) telah dinyatakan valid dan sesuai dengan fungsionalitas serta parameter teknis yang berjalan di sistem produksi saat ini.
