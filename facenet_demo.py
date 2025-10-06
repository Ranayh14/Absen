#!/usr/bin/env python3
"""
FaceNet Demo

This script demonstrates the FaceNet face recognition capabilities.
"""

import os
import sys
import json
import base64
import time
from PIL import Image
import io

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

def create_test_image(width=224, height=224, color='red'):
    """Create a test image for demonstration."""
    img = Image.new('RGB', (width, height), color=color)
    
    # Convert to base64
    buffer = io.BytesIO()
    img.save(buffer, format='JPEG')
    img_str = base64.b64encode(buffer.getvalue()).decode()
    
    return f"data:image/jpeg;base64,{img_str}"

def demo_embedding_generation():
    """Demonstrate face embedding generation."""
    print("=== Face Embedding Generation Demo ===")
    
    try:
        from facenet_service import FaceNetService
        
        # Initialize service
        service = FaceNetService()
        print("✓ FaceNet service initialized")
        
        # Create test image
        test_image = create_test_image()
        print("✓ Test image created")
        
        # Generate embedding
        start_time = time.time()
        embedding = service.generate_embedding(test_image)
        end_time = time.time()
        
        if embedding:
            print(f"✓ Embedding generated successfully")
            print(f"  - Dimensions: {len(embedding)}")
            print(f"  - Time taken: {end_time - start_time:.3f} seconds")
            print(f"  - First 5 values: {embedding[:5]}")
        else:
            print("✗ Failed to generate embedding")
        
    except Exception as e:
        print(f"✗ Error: {e}")

def demo_face_recognition():
    """Demonstrate face recognition."""
    print("\n=== Face Recognition Demo ===")
    
    try:
        from facenet_service import FaceNetService
        
        # Initialize service
        service = FaceNetService()
        print("✓ FaceNet service initialized")
        
        # Create test image
        test_image = create_test_image()
        print("✓ Test image created")
        
        # Recognize face
        start_time = time.time()
        result = service.recognize_face(test_image)
        end_time = time.time()
        
        if result:
            print(f"✓ Face recognition completed")
            print(f"  - Time taken: {end_time - start_time:.3f} seconds")
            print(f"  - Result: {result}")
        else:
            print("✗ Face recognition failed")
        
    except Exception as e:
        print(f"✗ Error: {e}")

def demo_attendance_processing():
    """Demonstrate attendance processing."""
    print("\n=== Attendance Processing Demo ===")
    
    try:
        from facenet_service import FaceNetService
        
        # Initialize service
        service = FaceNetService()
        print("✓ FaceNet service initialized")
        
        # Create test image
        test_image = create_test_image()
        print("✓ Test image created")
        
        # Process attendance
        start_time = time.time()
        result = service.process_attendance(test_image)
        end_time = time.time()
        
        if result:
            print(f"✓ Attendance processing completed")
            print(f"  - Time taken: {end_time - start_time:.3f} seconds")
            print(f"  - Result: {result}")
        else:
            print("✗ Attendance processing failed")
        
    except Exception as e:
        print(f"✗ Error: {e}")

def demo_database_operations():
    """Demonstrate database operations."""
    print("\n=== Database Operations Demo ===")
    
    try:
        from facenet_database import db
        
        # Test connection
        if db.is_connected():
            print("✓ Database connection successful")
            
            # Get stats
            stats = db.get_embedding_stats()
            print(f"✓ Embedding stats: {stats}")
            
            # Get all embeddings
            embeddings = db.get_all_embeddings()
            print(f"✓ Loaded {len(embeddings)} embeddings")
            
        else:
            print("✗ Database connection failed")
        
    except Exception as e:
        print(f"✗ Error: {e}")

def demo_cli_interface():
    """Demonstrate CLI interface."""
    print("\n=== CLI Interface Demo ===")
    
    try:
        import subprocess
        
        # Test data
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
            response = json.loads(result.stdout)
            print(f"  - Response: {response}")
        else:
            print("✗ CLI test failed")
            print(f"  - Error: {result.stderr}")
        
    except Exception as e:
        print(f"✗ Error: {e}")

def demo_api_endpoint():
    """Demonstrate API endpoint."""
    print("\n=== API Endpoint Demo ===")
    
    try:
        import subprocess
        
        # Test data
        test_data = {
            'action': 'generate_embedding',
            'image': create_test_image()
        }
        
        # Run API
        result = subprocess.run([
            'curl', '-X', 'POST', 'http://localhost/facenet_api.php',
            '-d', f"action={test_data['action']}&image={test_data['image']}"
        ], capture_output=True, text=True)
        
        if result.returncode == 0:
            print("✓ API test successful")
            response = json.loads(result.stdout)
            print(f"  - Response: {response}")
        else:
            print("✗ API test failed")
            print(f"  - Error: {result.stderr}")
        
    except Exception as e:
        print(f"✗ Error: {e}")

def demo_performance():
    """Demonstrate performance metrics."""
    print("\n=== Performance Demo ===")
    
    try:
        from facenet_service import FaceNetService
        
        # Initialize service
        service = FaceNetService()
        print("✓ FaceNet service initialized")
        
        # Test multiple images
        num_images = 5
        total_time = 0
        
        for i in range(num_images):
            test_image = create_test_image()
            
            start_time = time.time()
            embedding = service.generate_embedding(test_image)
            end_time = time.time()
            
            if embedding:
                elapsed = end_time - start_time
                total_time += elapsed
                print(f"  - Image {i+1}: {elapsed:.3f} seconds")
            else:
                print(f"  - Image {i+1}: Failed")
        
        if total_time > 0:
            avg_time = total_time / num_images
            print(f"✓ Average processing time: {avg_time:.3f} seconds")
            print(f"✓ Total time: {total_time:.3f} seconds")
        
    except Exception as e:
        print(f"✗ Error: {e}")

def main():
    """Run all demos."""
    print("FaceNet Integration Demo")
    print("=" * 50)
    
    # Run demos
    demo_embedding_generation()
    demo_face_recognition()
    demo_attendance_processing()
    demo_database_operations()
    demo_cli_interface()
    demo_api_endpoint()
    demo_performance()
    
    print("\n" + "=" * 50)
    print("Demo completed!")

if __name__ == '__main__':
    main()
