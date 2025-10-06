#!/usr/bin/env python3
"""
Download FaceNet Models

This script downloads the necessary pre-trained models for FaceNet.
"""

import os
import sys
import urllib.request
import zipfile
import shutil

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

def download_file(url, filename):
    """Download a file from URL."""
    print(f"Downloading {filename}...")
    try:
        urllib.request.urlretrieve(url, filename)
        print(f"Downloaded {filename}")
        return True
    except Exception as e:
        print(f"Error downloading {filename}: {e}")
        return False

def extract_zip(zip_path, extract_to):
    """Extract a zip file."""
    print(f"Extracting {zip_path}...")
    try:
        with zipfile.ZipFile(zip_path, 'r') as zip_ref:
            zip_ref.extractall(extract_to)
        print(f"Extracted {zip_path}")
        return True
    except Exception as e:
        print(f"Error extracting {zip_path}: {e}")
        return False

def main():
    """Download and setup FaceNet models."""
    print("Setting up FaceNet models...")
    
    # Create models directory
    models_dir = os.path.join('facenet-master', 'models')
    os.makedirs(models_dir, exist_ok=True)
    
    # Model URLs (these are example URLs, you may need to update them)
    models = {
        'facenet_keras.h5': 'https://github.com/nyoki-mtl/keras-facenet/releases/download/v0.0.1/facenet_keras.h5',
        'mtcnn_weights.zip': 'https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.zip'
    }
    
    # Download models
    for model_name, url in models.items():
        model_path = os.path.join(models_dir, model_name)
        
        if not os.path.exists(model_path):
            if download_file(url, model_path):
                # Extract if it's a zip file
                if model_name.endswith('.zip'):
                    extract_dir = os.path.join(models_dir, model_name.replace('.zip', ''))
                    if extract_zip(model_path, extract_dir):
                        # Remove zip file after extraction
                        os.remove(model_path)
            else:
                print(f"Failed to download {model_name}")
        else:
            print(f"{model_name} already exists")
    
    print("FaceNet models setup complete!")

if __name__ == '__main__':
    main()