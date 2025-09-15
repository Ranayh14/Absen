# Database Backup System

Sistem backup database otomatis untuk aplikasi absensi.

## Deskripsi

Sistem ini secara otomatis membuat backup database setiap kali ada perubahan data (INSERT, UPDATE, DELETE) pada aplikasi absensi. File backup disimpan dalam format SQL dan selalu mengganti file backup sebelumnya untuk menghemat ruang penyimpanan.

## Fitur

- ✅ Backup otomatis setiap ada perubahan data
- ✅ File backup tunggal (mengganti file lama)
- ✅ Format SQL yang dapat di-restore
- ✅ Logging error untuk troubleshooting
- ✅ Backup semua tabel dan data

## File yang Terlibat

- `database_backup.php` - File utama sistem backup
- `database_backup/` - Folder penyimpanan backup
- `database_backup/absen_db_backup.sql` - File backup database

## Cara Kerja

1. Setiap operasi database yang mengubah data (INSERT, UPDATE, DELETE) akan memanggil fungsi `triggerDatabaseBackup()`
2. Fungsi ini akan:
   - Menghapus file backup lama (jika ada)
   - Membuat backup baru dengan semua tabel dan data
   - Menyimpan file dengan nama `absen_db_backup.sql`
   - Mencatat log jika ada error

## Operasi yang Memicu Backup

- Registrasi user baru
- Update data user
- Hapus user
- Presensi masuk/pulang
- Hapus data presensi
- Tambah/edit data absence
- Update settings
- Semua operasi daily reports
- Semua operasi monthly reports

## Testing

Untuk test manual sistem backup:

```bash
php database_backup.php
```

## Restore Database

Untuk restore database dari backup:

```bash
mysql -u root -p absen_db < database_backup/absen_db_backup.sql
```

## Monitoring

- Log error tersimpan di error log PHP
- File backup dapat dicek ukuran dan timestamp terakhir
- Sistem backup berjalan otomatis tanpa intervensi manual

## Troubleshooting

Jika backup gagal:
1. Pastikan folder `database_backup/` dapat ditulis
2. Cek koneksi database
3. Lihat error log PHP untuk detail error
4. Pastikan MySQL service berjalan

## Catatan

- File backup hanya ada 1 file (terbaru)
- Backup mencakup semua tabel dan data
- Sistem backup tidak mempengaruhi performa aplikasi secara signifikan
- Backup file dapat di-restore ke database lain jika diperlukan
