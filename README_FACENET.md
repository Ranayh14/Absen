# FaceNet Integration

This document describes the FaceNet integration for the attendance system.

## Overview

The system now uses FaceNet for face recognition instead of face-api.js. FaceNet provides more accurate face recognition by generating high-dimensional face embeddings.

## Architecture

```
index.php (Main Application)
    ↓ (AJAX calls)
facenet_api.php (PHP API)
    ↓ (shell_exec)
facenet_cli.py (Python CLI)
    ↓ (imports)
facenet_service.py (Python Service)
    ↓ (uses)
facenet-master/ (FaceNet Implementation)
```

## Files

### Core Files
- `facenet_service.py` - Main FaceNet service with face detection and recognition
- `facenet_cli.py` - Command-line interface for the FaceNet service
- `facenet_api.php` - PHP API endpoint that bridges PHP and Python
- `setup_facenet.py` - Setup script for FaceNet environment
- `download_facenet_models.py` - Script to download required models
- `requirements.txt` - Python dependencies

### Database Changes
The `users` table has been updated with new columns:
- `face_embedding` (LONGTEXT) - Stores the face embedding as JSON
- `face_embedding_updated` (TIMESTAMP) - When the embedding was last updated

## Setup

1. **Install Python Dependencies**
   ```bash
   pip install -r requirements.txt
   ```

2. **Run Setup Script**
   ```bash
   python setup_facenet.py
   ```

3. **Download Models**
   ```bash
   python download_facenet_models.py
   ```

## API Endpoints

### Generate Face Embedding
- **URL**: `index.php?ajax=generate_face_embedding`
- **Method**: POST
- **Parameters**: `image` (base64 encoded image)
- **Response**: Success/error message

### Recognize Face
- **URL**: `index.php?ajax=recognize_face`
- **Method**: POST
- **Parameters**: `image` (base64 encoded image), `threshold` (optional, default 1.0)
- **Response**: Recognition result with user information

### Process Attendance
- **URL**: `index.php?ajax=process_attendance_facenet`
- **Method**: POST
- **Parameters**: `image` (base64 encoded image), `threshold` (optional, default 1.0)
- **Response**: Attendance processing result

## Usage

### Generating Face Embeddings
```javascript
// Capture image from camera
const canvas = document.getElementById('camera');
const base64Image = canvas.toDataURL('image/jpeg');

// Send to server
fetch('index.php?ajax=generate_face_embedding', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `image=${encodeURIComponent(base64Image)}`
})
.then(response => response.json())
.then(data => {
    if (data.ok) {
        console.log('Face embedding generated successfully');
    } else {
        console.error('Error:', data.error);
    }
});
```

### Recognizing Faces
```javascript
// Capture image from camera
const canvas = document.getElementById('camera');
const base64Image = canvas.toDataURL('image/jpeg');

// Send to server
fetch('index.php?ajax=recognize_face', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `image=${encodeURIComponent(base64Image)}&threshold=1.0`
})
.then(response => response.json())
.then(data => {
    if (data.ok && data.data.recognized) {
        console.log('User recognized:', data.data.user_id);
        console.log('Confidence:', data.data.confidence);
    } else {
        console.log('User not recognized');
    }
});
```

## Configuration

### Threshold Values
- **0.6**: Very strict recognition (high accuracy, may reject valid users)
- **0.8**: Strict recognition (good balance)
- **1.0**: Default threshold (balanced)
- **1.2**: Loose recognition (may accept invalid users)

### Model Paths
The system looks for models in:
- `facenet-master/models/20180402-114759/` - FaceNet model
- `facenet-master/models/mtcnn_weights/` - MTCNN model

## Troubleshooting

### Common Issues

1. **Import Errors**
   - Ensure all Python dependencies are installed
   - Check that the facenet-master directory is in the correct location

2. **Model Not Found**
   - Run `python download_facenet_models.py` to download models
   - Check that model files are in the correct directories

3. **Permission Errors**
   - Ensure the web server can execute Python scripts
   - Check file permissions on Python files

4. **Memory Issues**
   - FaceNet models require significant memory
   - Consider using a server with at least 4GB RAM

### Debug Mode
Enable debug mode by setting `DEBUG = True` in `facenet_service.py` to see detailed error messages.

## Performance

- **Face Detection**: ~200-500ms per image
- **Embedding Generation**: ~100-300ms per face
- **Face Recognition**: ~300-800ms per image
- **Memory Usage**: ~2-4GB for models

## Security Considerations

- Face embeddings are stored as JSON in the database
- Images are processed in memory and not stored permanently
- The system uses HTTPS for secure communication
- Face recognition results are logged for audit purposes

## Future Improvements

1. **Batch Processing**: Process multiple faces simultaneously
2. **Model Optimization**: Use quantized models for faster inference
3. **Caching**: Cache embeddings to reduce computation
4. **Real-time Processing**: Optimize for real-time face recognition
5. **Multi-face Support**: Handle multiple faces in a single image
