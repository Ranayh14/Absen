# FaceNet Enhanced Recognition System

## Overview

Sistem FaceNet Enhanced adalah peningkatan dari sistem face recognition sebelumnya yang menggunakan analisis fitur wajah yang lebih detail untuk meningkatkan akurasi pengenalan wajah. Sistem ini menggabungkan FaceNet embedding tradisional dengan analisis geometri wajah, landmark detection, dan fitur-fitur detail seperti ukuran lebar muka, lebar dahi, bentuk muka, bentuk hidung, dan karakteristik lainnya.

## Fitur Utama

### 1. **Analisis Geometri Wajah**
- **Ukuran Wajah**: Lebar dan tinggi wajah
- **Rasio Wajah**: Perbandingan lebar terhadap tinggi
- **Lebar Dahi**: Pengukuran lebar dahi
- **Rasio Dahi**: Perbandingan lebar dahi terhadap lebar wajah

### 2. **Analisis Bentuk Wajah**
- **Klasifikasi Bentuk**: Round, Square, Oval, Heart, Diamond
- **Analisis Rahang**: Square, Rounded, Pointed
- **Analisis Tulang Pipi**: Lebar dan proporsi

### 3. **Analisis Fitur Individual**
- **Mata**: 
  - Lebar dan tinggi mata
  - Rasio mata
  - Jarak antar mata
  - Bentuk mata (Almond, Round, Oval)
  
- **Hidung**:
  - Lebar dan tinggi hidung
  - Rasio hidung
  - Bentuk hidung (Wide, Medium, Narrow)
  
- **Mulut**:
  - Lebar dan tinggi mulut
  - Rasio mulut
  - Bentuk mulut (Wide, Medium, Narrow)

### 4. **Analisis Simetri dan Sudut**
- **Skor Simetri**: Pengukuran simetri wajah
- **Sudut Wajah**: Rotasi dan orientasi wajah
- **Landmark Detection**: Deteksi 68 titik landmark wajah

## Arsitektur Sistem

### File-file Utama

1. **`facenet_advanced_features.py`**
   - Analisis fitur wajah advanced
   - Deteksi landmark wajah
   - Analisis geometri dan proporsi

2. **`facenet_enhanced_service.py`**
   - Service utama untuk enhanced recognition
   - Kombinasi FaceNet embedding dengan fitur advanced
   - Algoritma perbandingan yang lebih akurat

3. **`facenet_enhanced_api.php`**
   - API endpoint untuk enhanced FaceNet
   - Bridge antara PHP dan Python

4. **`facenet_enhanced_cli.py`**
   - Command-line interface untuk enhanced service

### Database Schema

Tabel `users` telah diperluas dengan kolom-kolom berikut:

```sql
ALTER TABLE users ADD COLUMN advanced_features LONGTEXT NULL;
ALTER TABLE users ADD COLUMN facial_geometry LONGTEXT NULL;
ALTER TABLE users ADD COLUMN feature_vector LONGTEXT NULL;
```

## Cara Kerja

### 1. **Registration/Update Photo**
```
User Upload Foto → Frontend → index.php → facenet_enhanced_api.php → facenet_enhanced_cli.py → EnhancedFaceNetService
```

Proses:
1. User upload foto
2. Frontend kirim base64 image ke `index.php`
3. `index.php` forward ke `facenet_enhanced_api.php`
4. API execute `facenet_enhanced_cli.py` dengan action `save_enhanced_embedding`
5. Service generate:
   - Base FaceNet embedding
   - Advanced facial features
   - Facial geometry analysis
   - Feature vector
6. Semua data disimpan ke database

### 2. **Attendance/Recognition**
```
User Capture Foto → Frontend → index.php → facenet_enhanced_api.php → facenet_enhanced_cli.py → EnhancedFaceNetService
```

Proses:
1. User capture foto untuk attendance
2. Frontend kirim base64 image ke `index.php`
3. `index.php` forward ke `facenet_enhanced_api.php`
4. API execute `facenet_enhanced_cli.py` dengan action `process_enhanced_attendance`
5. Service:
   - Generate enhanced embedding untuk input image
   - Compare dengan semua known faces di database
   - Hitung similarity score menggunakan multiple features
   - Return hasil recognition dengan confidence score

## Algoritma Perbandingan

### Weighted Similarity Score

Sistem menggunakan weighted combination dari multiple features:

```python
combined_score = (
    facenet_similarity * 0.5 +      # FaceNet embedding (50%)
    advanced_similarity * 0.2 +     # Advanced features (20%)
    geometric_similarity * 0.3      # Geometric analysis (30%)
)
```

### Feature Comparison

1. **FaceNet Embedding**: Euclidean distance comparison
2. **Geometric Features**: Ratio dan proporsi comparison
3. **Categorical Features**: Exact match untuk shape classification
4. **Symmetry Score**: Facial symmetry analysis

## Peningkatan Akurasi

### Dibandingkan dengan Sistem Sebelumnya:

1. **Multiple Feature Analysis**: 
   - Sebelum: Hanya FaceNet embedding (512 dimensions)
   - Sekarang: FaceNet + Geometric + Categorical features (100+ dimensions)

2. **Detailed Facial Analysis**:
   - Sebelum: General face recognition
   - Sekarang: Specific feature analysis (eyes, nose, mouth, jaw, forehead)

3. **Improved Threshold**:
   - Sebelum: Single threshold untuk semua cases
   - Sekarang: Adaptive threshold berdasarkan feature quality

4. **Confidence Scoring**:
   - Sebelum: Binary recognition (recognized/not recognized)
   - Sekarang: Confidence score dengan detailed analysis

## API Endpoints

### Enhanced FaceNet Endpoints

1. **`generate_enhanced_face_embedding`**
   - Generate enhanced embedding dengan advanced features
   - Method: POST
   - Parameters: `image` (base64)

2. **`recognize_enhanced_face`**
   - Recognize face menggunakan enhanced features
   - Method: POST
   - Parameters: `image` (base64), `threshold` (optional)

3. **`process_enhanced_attendance`**
   - Process attendance dengan enhanced recognition
   - Method: POST
   - Parameters: `image` (base64), `threshold` (optional)

## Konfigurasi

### Feature Weights
```python
feature_weights = {
    'facenet_embedding': 0.5,      # 50% - FaceNet embedding
    'facial_geometry': 0.3,        # 30% - Geometric features
    'facial_features': 0.2         # 20% - Individual features
}
```

### Threshold Settings
```python
DEFAULT_THRESHOLD = 1.0            # Default recognition threshold
NORMALIZE_EMBEDDINGS = True        # Normalize embeddings
RECOGNITION_METHOD = 'euclidean'   # Distance calculation method
```

## Instalasi dan Setup

### 1. **Dependencies**
```bash
pip install opencv-python dlib numpy scipy scikit-learn
```

### 2. **Download Models**
```bash
python download_facenet_models.py
```

### 3. **Database Setup**
```sql
-- Kolom sudah ditambahkan otomatis oleh index.php
-- Tidak perlu setup manual
```

### 4. **Testing**
```bash
python facenet_enhanced_service.py
```

## Monitoring dan Debugging

### Debug Features
- `DEBUG = True`: Enable debug mode
- `SAVE_DEBUG_IMAGES = True`: Save debug images
- `DEBUG_IMAGE_PATH = '/tmp'`: Debug image location

### Logging
```python
import logging
logging.basicConfig(level=logging.INFO)
```

### Statistics
```python
stats = enhanced_service.get_enhanced_embedding_stats()
print(f"Total faces: {stats['total_faces']}")
print(f"Faces with advanced features: {stats['faces_with_advanced_features']}")
print(f"Average feature count: {stats['average_feature_count']}")
```

## Troubleshooting

### Common Issues

1. **Import Error**
   ```
   Error: Failed to import EnhancedFaceNetService
   Solution: Pastikan semua dependencies terinstall
   ```

2. **Database Connection**
   ```
   Error: Error connecting to MySQL
   Solution: Check database credentials di facenet_config.py
   ```

3. **Model Loading**
   ```
   Error: Failed to load FaceNet models
   Solution: Run download_facenet_models.py
   ```

### Performance Optimization

1. **Memory Usage**: Monitor memory usage untuk large datasets
2. **Processing Time**: Enhanced features membutuhkan waktu lebih lama
3. **Database Size**: Advanced features membutuhkan storage lebih besar

## Future Enhancements

1. **Machine Learning**: Implementasi ML untuk adaptive threshold
2. **Real-time Processing**: Optimasi untuk real-time recognition
3. **Cloud Integration**: Support untuk cloud-based processing
4. **Mobile Support**: Optimasi untuk mobile devices

## Support

Untuk pertanyaan atau masalah, silakan check:
1. Log files di `/var/log/facenet/`
2. Debug images di `DEBUG_IMAGE_PATH`
3. Database connection status
4. Model loading status
