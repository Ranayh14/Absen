#!/usr/bin/env python3
"""
Test FaceNet Service

This script tests the FaceNet service functionality.
"""

import os
import sys
import json
import base64
from PIL import Image
import io

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

def create_test_image():
    """Create a test image for testing."""
    # Create a simple test image
    img = Image.new('RGB', (224, 224), color='red')
    
    # Convert to base64
    buffer = io.BytesIO()
    img.save(buffer, format='JPEG')
    img_str = base64.b64encode(buffer.getvalue()).decode()
    
    return img_str

def test_facenet_service():
    """Test the FaceNet service."""
    print("Testing FaceNet service...")
    
    try:
        from facenet_service import FaceNetService
        
        # Initialize service
        print("Initializing FaceNet service...")
        service = FaceNetService()
        print("✓ FaceNet service initialized successfully")
        
        # Create test image
        print("Creating test image...")
        test_image = create_test_image()
        print("✓ Test image created")
        
        # Test embedding generation
        print("Testing embedding generation...")
        embedding = service.generate_embedding(test_image)
        if embedding:
            print(f"✓ Embedding generated successfully (length: {len(embedding)})")
        else:
            print("✗ Failed to generate embedding")
        
        # Test face recognition
        print("Testing face recognition...")
        result = service.recognize_face(test_image)
        if result:
            print(f"✓ Face recognition completed: {result}")
        else:
            print("✗ Face recognition failed")
        
        # Test attendance processing
        print("Testing attendance processing...")
        result = service.process_attendance(test_image)
        if result:
            print(f"✓ Attendance processing completed: {result}")
        else:
            print("✗ Attendance processing failed")
        
        print("\nFaceNet service test completed!")
        
    except ImportError as e:
        print(f"✗ Import error: {e}")
        print("Make sure all dependencies are installed and facenet-master is available")
    except Exception as e:
        print(f"✗ Error: {e}")
        import traceback
        traceback.print_exc()

def test_cli():
    """Test the CLI interface."""
    print("\nTesting CLI interface...")
    
    try:
        import subprocess
        
        # Create test data
        test_data = {
            'action': 'generate_embedding',
            'image': create_test_image(),
            'threshold': 1.0
        }
        
        # Run CLI
        result = subprocess.run([
            'python', 'facenet_cli.py', 
            json.dumps(test_data)
        ], capture_output=True, text=True)
        
        if result.returncode == 0:
            print("✓ CLI test successful")
            print(f"Output: {result.stdout}")
        else:
            print("✗ CLI test failed")
            print(f"Error: {result.stderr}")
    
    except Exception as e:
        print(f"✗ CLI test error: {e}")

def main():
    """Run all tests."""
    print("FaceNet Integration Tests")
    print("=" * 50)
    
    # Test service
    test_facenet_service()
    
    # Test CLI
    test_cli()
    
    print("\n" + "=" * 50)
    print("Tests completed!")

if __name__ == '__main__':
    main()
