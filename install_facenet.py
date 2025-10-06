#!/usr/bin/env python3
"""
Install FaceNet

This script installs and sets up FaceNet for the attendance system.
"""

import os
import sys
import subprocess
import urllib.request
import zipfile
import shutil
import json

def run_command(command, description=""):
    """Run a shell command and return the result."""
    print(f"Running: {description or command}")
    try:
        result = subprocess.run(command, shell=True, capture_output=True, text=True)
        if result.returncode == 0:
            print(f"✓ {description or command}")
            return True
        else:
            print(f"✗ {description or command}")
            print(f"Error: {result.stderr}")
            return False
    except Exception as e:
        print(f"✗ {description or command}")
        print(f"Exception: {e}")
        return False

def download_file(url, filename, description=""):
    """Download a file from URL."""
    print(f"Downloading {description or filename}...")
    try:
        urllib.request.urlretrieve(url, filename)
        print(f"✓ Downloaded {description or filename}")
        return True
    except Exception as e:
        print(f"✗ Error downloading {description or filename}: {e}")
        return False

def extract_zip(zip_path, extract_to, description=""):
    """Extract a zip file."""
    print(f"Extracting {description or zip_path}...")
    try:
        with zipfile.ZipFile(zip_path, 'r') as zip_ref:
            zip_ref.extractall(extract_to)
        print(f"✓ Extracted {description or zip_path}")
        return True
    except Exception as e:
        print(f"✗ Error extracting {description or zip_path}: {e}")
        return False

def check_python_version():
    """Check if Python version is compatible."""
    version = sys.version_info
    if version.major < 3 or (version.major == 3 and version.minor < 6):
        print("✗ Python 3.6+ is required")
        return False
    print(f"✓ Python {version.major}.{version.minor}.{version.micro} is compatible")
    return True

def check_dependencies():
    """Check if required dependencies are available."""
    required_packages = [
        'tensorflow',
        'numpy',
        'PIL',
        'opencv-python',
        'scipy',
        'scikit-learn'
    ]
    
    missing_packages = []
    for package in required_packages:
        try:
            __import__(package)
            print(f"✓ {package} is available")
        except ImportError:
            print(f"✗ {package} is missing")
            missing_packages.append(package)
    
    return missing_packages

def install_dependencies():
    """Install Python dependencies."""
    print("\nInstalling Python dependencies...")
    
    # Install from requirements.txt
    if os.path.exists('requirements.txt'):
        if run_command("pip install -r requirements.txt", "Installing dependencies from requirements.txt"):
            return True
    
    # Install individual packages
    packages = [
        'tensorflow==1.7',
        'numpy',
        'Pillow',
        'opencv-python',
        'scipy',
        'scikit-learn',
        'h5py',
        'matplotlib',
        'requests',
        'psutil'
    ]
    
    for package in packages:
        if not run_command(f"pip install {package}", f"Installing {package}"):
            return False
    
    return True

def setup_directories():
    """Create necessary directories."""
    print("\nCreating directories...")
    
    directories = [
        'facenet-master/models',
        'facenet-master/data',
        'debug_images',
        'logs'
    ]
    
    for directory in directories:
        os.makedirs(directory, exist_ok=True)
        print(f"✓ Created directory: {directory}")

def download_models():
    """Download FaceNet models."""
    print("\nDownloading FaceNet models...")
    
    models_dir = 'facenet-master/models'
    
    # Download pre-trained FaceNet model
    facenet_model_url = 'https://github.com/davidsandberg/facenet/releases/download/v1.0/20180402-114759.zip'
    facenet_model_zip = os.path.join(models_dir, 'facenet_model.zip')
    
    if not os.path.exists(os.path.join(models_dir, '20180402-114759')):
        if download_file(facenet_model_url, facenet_model_zip, "FaceNet model"):
            if extract_zip(facenet_model_zip, models_dir, "FaceNet model"):
                os.remove(facenet_model_zip)
    
    # Download MTCNN model
    mtcnn_model_url = 'https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.zip'
    mtcnn_model_zip = os.path.join(models_dir, 'mtcnn_weights.zip')
    
    if not os.path.exists(os.path.join(models_dir, 'mtcnn_weights')):
        if download_file(mtcnn_model_url, mtcnn_model_zip, "MTCNN model"):
            if extract_zip(mtcnn_model_zip, models_dir, "MTCNN model"):
                os.remove(mtcnn_model_zip)

def test_installation():
    """Test the FaceNet installation."""
    print("\nTesting FaceNet installation...")
    
    # Test Python imports
    try:
        import tensorflow as tf
        import numpy as np
        from PIL import Image
        import cv2
        print("✓ All required packages imported successfully")
    except ImportError as e:
        print(f"✗ Import error: {e}")
        return False
    
    # Test FaceNet service
    if run_command("python facenet_service.py", "Testing FaceNet service"):
        print("✓ FaceNet service test passed")
    else:
        print("✗ FaceNet service test failed")
        return False
    
    # Test CLI
    test_data = {
        'action': 'generate_embedding',
        'image': 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A'
    }
    
    if run_command(f'python facenet_cli.py "{json.dumps(test_data)}"', "Testing FaceNet CLI"):
        print("✓ FaceNet CLI test passed")
    else:
        print("✗ FaceNet CLI test failed")
        return False
    
    return True

def create_config():
    """Create configuration file."""
    print("\nCreating configuration...")
    
    config = {
        'database': {
            'host': 'localhost',
            'name': 'absen_db',
            'user': 'root',
            'password': ''
        },
        'facenet': {
            'model_path': 'facenet-master/models/20180402-114759',
            'mtcnn_path': 'facenet-master/models/mtcnn_weights',
            'threshold': 1.0,
            'normalize_embeddings': True
        },
        'api': {
            'timeout': 30,
            'max_image_size': 10485760
        },
        'debug': {
            'enabled': False,
            'save_images': False,
            'log_level': 'INFO'
        }
    }
    
    with open('facenet_config.json', 'w') as f:
        json.dump(config, f, indent=2)
    
    print("✓ Configuration file created: facenet_config.json")

def main():
    """Main installation function."""
    print("FaceNet Installation Script")
    print("=" * 50)
    
    # Check Python version
    if not check_python_version():
        print("Please upgrade Python to version 3.6 or higher")
        return False
    
    # Check dependencies
    missing_packages = check_dependencies()
    if missing_packages:
        print(f"Missing packages: {', '.join(missing_packages)}")
        if not install_dependencies():
            print("Failed to install dependencies")
            return False
    
    # Setup directories
    setup_directories()
    
    # Download models
    download_models()
    
    # Create configuration
    create_config()
    
    # Test installation
    if test_installation():
        print("\n" + "=" * 50)
        print("✓ FaceNet installation completed successfully!")
        print("\nNext steps:")
        print("1. Make sure your web server can execute Python scripts")
        print("2. Test the FaceNet API by calling facenet_api.php")
        print("3. Update your database with face embeddings for existing users")
        print("4. Configure the system settings in facenet_config.json")
        return True
    else:
        print("\n" + "=" * 50)
        print("✗ FaceNet installation failed!")
        print("Please check the error messages above and try again")
        return False

if __name__ == '__main__':
    success = main()
    sys.exit(0 if success else 1)
