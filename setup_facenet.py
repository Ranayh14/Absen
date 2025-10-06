#!/usr/bin/env python3
"""
Setup FaceNet

This script sets up the FaceNet environment and downloads necessary models.
"""

import os
import sys
import subprocess
import urllib.request
import zipfile

def run_command(command):
    """Run a shell command and return the result."""
    try:
        result = subprocess.run(command, shell=True, capture_output=True, text=True)
        if result.returncode == 0:
            print(f"✓ {command}")
            return True
        else:
            print(f"✗ {command}")
            print(f"Error: {result.stderr}")
            return False
    except Exception as e:
        print(f"✗ {command}")
        print(f"Exception: {e}")
        return False

def download_file(url, filename):
    """Download a file from URL."""
    print(f"Downloading {filename}...")
    try:
        urllib.request.urlretrieve(url, filename)
        print(f"✓ Downloaded {filename}")
        return True
    except Exception as e:
        print(f"✗ Error downloading {filename}: {e}")
        return False

def extract_zip(zip_path, extract_to):
    """Extract a zip file."""
    print(f"Extracting {zip_path}...")
    try:
        with zipfile.ZipFile(zip_path, 'r') as zip_ref:
            zip_ref.extractall(extract_to)
        print(f"✓ Extracted {zip_path}")
        return True
    except Exception as e:
        print(f"✗ Error extracting {zip_path}: {e}")
        return False

def main():
    """Setup FaceNet environment."""
    print("Setting up FaceNet environment...")
    
    # Check if Python is available
    if not run_command("python --version"):
        print("Python is not available. Please install Python 3.6+ and try again.")
        return
    
    # Install Python dependencies
    print("\nInstalling Python dependencies...")
    if not run_command("pip install -r requirements.txt"):
        print("Failed to install Python dependencies.")
        return
    
    # Create necessary directories
    print("\nCreating directories...")
    os.makedirs('facenet-master/models', exist_ok=True)
    os.makedirs('facenet-master/data', exist_ok=True)
    
    # Download FaceNet models
    print("\nDownloading FaceNet models...")
    models_dir = 'facenet-master/models'
    
    # Download pre-trained FaceNet model
    facenet_model_url = 'https://github.com/davidsandberg/facenet/releases/download/v1.0/20180402-114759.zip'
    facenet_model_zip = os.path.join(models_dir, 'facenet_model.zip')
    
    if not os.path.exists(os.path.join(models_dir, '20180402-114759')):
        if download_file(facenet_model_url, facenet_model_zip):
            if extract_zip(facenet_model_zip, models_dir):
                os.remove(facenet_model_zip)
    
    # Download MTCNN model
    mtcnn_model_url = 'https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.zip'
    mtcnn_model_zip = os.path.join(models_dir, 'mtcnn_weights.zip')
    
    if not os.path.exists(os.path.join(models_dir, 'mtcnn_weights')):
        if download_file(mtcnn_model_url, mtcnn_model_zip):
            if extract_zip(mtcnn_model_zip, models_dir):
                os.remove(mtcnn_model_zip)
    
    # Test FaceNet service
    print("\nTesting FaceNet service...")
    if run_command("python facenet_service.py"):
        print("✓ FaceNet service is working correctly")
    else:
        print("✗ FaceNet service test failed")
    
    print("\nFaceNet setup complete!")
    print("\nNext steps:")
    print("1. Make sure your web server can execute Python scripts")
    print("2. Test the FaceNet API by calling facenet_api.php")
    print("3. Update your database with face embeddings for existing users")

if __name__ == '__main__':
    main()
