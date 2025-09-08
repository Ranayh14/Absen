# Panduan Aplikasi Offline

Aplikasi presensi wajah ini telah dikonfigurasi untuk berfungsi secara offline menggunakan teknologi Progressive Web App (PWA).

## Fitur Offline

### 1. Service Worker
- **File**: `sw.js`
- **Fungsi**: Menyimpan cache semua aset penting (CSS, JS, font, model AI)
- **Manfaat**: Aplikasi tetap berfungsi meski tanpa internet

### 2. Manifest PWA
- **File**: `manifest.json`
- **Fungsi**: Membuat aplikasi bisa diinstall seperti aplikasi native
- **Manfaat**: Akses cepat dari home screen tanpa browser

### 3. Aset Lokal
Semua aset eksternal telah didownload dan disimpan lokal:
- ✅ Tailwind CSS (`assets/css/tailwind.min.css`)
- ✅ Face API (`assets/js/face-api.min.js`)
- ✅ Chart.js (`assets/js/chart.min.js`)
- ✅ Font Inter (`assets/css/inter.css`)
- ✅ Flaticon Icons (`assets/css/uicons-*.css`)
- ✅ Face API Models (`assets/js/face-api-models/`)

### 4. Avatar Generator Lokal
- **File**: `generate-avatar.php`
- **Fungsi**: Mengganti ui-avatars.com dengan generator lokal
- **Manfaat**: Avatar tetap muncul meski offline

### 5. Icon Generator
- **File**: `create-icon.php`
- **Fungsi**: Membuat icon PWA secara dinamis
- **Manfaat**: Tidak perlu file icon statis

## Cara Menggunakan

### 1. Install sebagai PWA
1. Buka aplikasi di browser
2. Klik tombol "Install" di address bar (Chrome/Edge)
3. Atau pilih "Add to Home Screen" di menu browser

### 2. Akses Offline
1. Pastikan aplikasi sudah dibuka minimal sekali saat online
2. Service Worker akan otomatis cache semua aset
3. Tutup browser dan matikan internet
4. Buka aplikasi lagi - tetap berfungsi!

### 3. Update Cache
- Service Worker otomatis update cache saat ada perubahan
- Versi cache: `absen-app-v1` (lihat di `sw.js`)

## Troubleshooting

### Cache tidak terupdate?
1. Buka Developer Tools (F12)
2. Tab Application > Storage > Clear Storage
3. Refresh halaman

### Service Worker error?
1. Buka Developer Tools (F12)
2. Tab Application > Service Workers
3. Unregister service worker
4. Refresh halaman

### Avatar tidak muncul?
- Pastikan `generate-avatar.php` bisa diakses
- Check console untuk error PHP

## File yang Ditambahkan/Diubah

### File Baru:
- `sw.js` - Service Worker
- `manifest.json` - PWA Manifest
- `generate-avatar.php` - Avatar generator lokal
- `create-icon.php` - Icon generator
- `OFFLINE-README.md` - Dokumentasi ini

### File yang Diubah:
- `index.php` - Update referensi CDN ke lokal, tambah PWA meta tags

### Aset yang Didownload:
- `assets/css/tailwind.min.css`
- `assets/js/face-api.min.js`
- `assets/js/chart.min.js`
- `assets/css/inter.css`
- `assets/css/uicons-solid-rounded.css`
- `assets/css/uicons-solid-straight.css`

## Testing Offline

1. **Online Test**: Buka aplikasi, pastikan semua berfungsi normal
2. **Cache Test**: Buka Developer Tools > Network, refresh, lihat "from cache"
3. **Offline Test**: Matikan internet, buka aplikasi, pastikan masih berfungsi
4. **PWA Test**: Install sebagai PWA, buka dari home screen

## Browser Support

- ✅ Chrome/Chromium (recommended)
- ✅ Edge
- ✅ Firefox
- ✅ Safari (iOS 11.3+)
- ⚠️ Internet Explorer (tidak support Service Worker)

## Performance

- **First Load**: Sama seperti sebelumnya
- **Subsequent Loads**: Lebih cepat karena cache
- **Offline**: Hampir instant loading
- **Storage**: ~5-10MB cache (tergantung model AI)
