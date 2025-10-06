# Panduan Instalasi FaceNet

Dokumen ini menjelaskan cara menginstal dan mengkonfigurasi FaceNet untuk sistem absensi.

## Persyaratan Sistem

### Perangkat Keras
- **RAM**: Minimal 4GB (Direkomendasikan 8GB+)
- **Storage**: Minimal 2GB ruang kosong
- **CPU**: Multi-core processor (Direkomendasikan Intel i5 atau setara)
- **GPU**: Opsional, untuk performa yang lebih baik

### Perangkat Lunak
- **Python**: 3.6 atau lebih tinggi
- **PHP**: 7.4 atau lebih tinggi
- **MySQL**: 5.7 atau lebih tinggi
- **Web Server**: Apache atau Nginx
- **Git**: Untuk mengunduh model

## Instalasi Otomatis

### 1. Jalankan Script Instalasi
```bash
python install_facenet.py
```

Script ini akan:
- Memeriksa versi Python
- Menginstal dependencies
- Membuat direktori yang diperlukan
- Mengunduh model FaceNet
- Membuat file konfigurasi
- Menjalankan tes instalasi

### 2. Verifikasi Instalasi
```bash
python test_facenet.py
```

## Instalasi Manual

### 1. Instal Python Dependencies
```bash
pip install -r requirements.txt
```

### 2. Buat Direktori
```bash
mkdir -p facenet-master/models
mkdir -p facenet-master/data
mkdir -p debug_images
mkdir -p logs
```

### 3. Unduh Model FaceNet
```bash
# FaceNet Model
wget https://github.com/davidsandberg/facenet/releases/download/v1.0/20180402-114759.zip
unzip 20180402-114759.zip -d facenet-master/models/

# MTCNN Model
wget https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.zip
unzip mtcnn_weights.zip -d facenet-master/models/
```

### 4. Konfigurasi Database
Pastikan database MySQL sudah berjalan dan dapat diakses:
```sql
-- Periksa apakah tabel users sudah memiliki kolom face_embedding
DESCRIBE users;

-- Jika belum, jalankan script di index.php untuk menambahkan kolom
```

### 5. Konfigurasi Web Server

#### Apache
Pastikan modul `mod_rewrite` dan `mod_headers` sudah diaktifkan:
```apache
# .htaccess
RewriteEngine On
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type"
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## Konfigurasi

### 1. File Konfigurasi
Edit `facenet_config.py` untuk menyesuaikan pengaturan:

```python
# Database settings
DB_HOST = 'localhost'
DB_NAME = 'absen_db'
DB_USER = 'root'
DB_PASS = ''

# Recognition settings
DEFAULT_THRESHOLD = 1.0
RECOGNITION_METHOD = 'euclidean'
NORMALIZE_EMBEDDINGS = True

# Performance settings
USE_GPU = False
BATCH_SIZE = 1
```

### 2. Konfigurasi PHP
Pastikan ekstensi PHP yang diperlukan sudah diaktifkan:
```ini
extension=curl
extension=json
extension=pdo_mysql
extension=mysqli
```

### 3. Konfigurasi MySQL
```sql
-- Pastikan charset database mendukung UTF-8
ALTER DATABASE absen_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Pastikan tabel users menggunakan charset yang benar
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Testing

### 1. Test Database Connection
```bash
python -c "from facenet_database import db; print('Database connected:', db.is_connected())"
```

### 2. Test FaceNet Service
```bash
python facenet_service.py
```

### 3. Test CLI Interface
```bash
python facenet_cli.py '{"action": "generate_embedding", "image": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A"}'
```

### 4. Test API Endpoint
```bash
curl -X POST http://localhost/facenet_api.php \
  -d "action=generate_embedding&image=data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A"
```

## Troubleshooting

### Masalah Umum

#### 1. Import Error
```
ImportError: No module named 'tensorflow'
```
**Solusi**: Instal TensorFlow
```bash
pip install tensorflow==1.7
```

#### 2. Model Not Found
```
FileNotFoundError: [Errno 2] No such file or directory: 'facenet-master/models/20180402-114759'
```
**Solusi**: Unduh model FaceNet
```bash
python download_facenet_models.py
```

#### 3. Database Connection Error
```
Error connecting to MySQL: Access denied for user 'root'@'localhost'
```
**Solusi**: Periksa kredensial database di `facenet_config.py`

#### 4. Permission Error
```
PermissionError: [Errno 13] Permission denied: 'facenet_api.php'
```
**Solusi**: Set permission yang benar
```bash
chmod 755 facenet_api.php
chmod 755 facenet_cli.py
chmod 755 facenet_service.py
```

#### 5. Memory Error
```
ResourceExhaustedError: OOM when allocating tensor
```
**Solusi**: 
- Kurangi batch size
- Gunakan model yang lebih kecil
- Tambah RAM

### Debug Mode

Aktifkan debug mode untuk melihat informasi detail:
```python
# facenet_config.py
DEBUG = True
SAVE_DEBUG_IMAGES = True
LOG_LEVEL = 'DEBUG'
```

### Log Files

Periksa log files untuk informasi error:
- `facenet.log` - Log FaceNet service
- `performance.log` - Log performa
- `php-error.log` - Log error PHP

## Optimasi Performa

### 1. GPU Acceleration
```python
# facenet_config.py
USE_GPU = True
GPU_MEMORY_FRACTION = 0.5
```

### 2. Model Optimization
```python
# Gunakan model yang lebih kecil
MODEL_VERSION = '0.5'  # Model 512MB instead of 1GB
```

### 3. Caching
```python
# facenet_config.py
ENABLE_CACHE = True
CACHE_SIZE = 1000
CACHE_TTL = 3600
```

### 4. Batch Processing
```python
# facenet_config.py
BATCH_SIZE = 4  # Process multiple images at once
```

## Maintenance

### 1. Backup Embeddings
```bash
python -c "from facenet_database import db; db.backup_embeddings('backup_embeddings.json')"
```

### 2. Cleanup Old Embeddings
```bash
python -c "from facenet_database import db; db.cleanup_old_embeddings(30)"
```

### 3. Update Models
```bash
python download_facenet_models.py --update
```

### 4. Monitor Performance
```bash
tail -f performance.log
```

## Support

Jika mengalami masalah, periksa:
1. Log files untuk error messages
2. Konfigurasi database dan web server
3. Versi Python dan dependencies
4. Permission files dan direktori

Untuk bantuan lebih lanjut, buat issue di repository atau hubungi administrator sistem.
