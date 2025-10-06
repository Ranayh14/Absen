#!/usr/bin/env python3
"""
FaceNet Benchmark

This script benchmarks the FaceNet face recognition performance.
"""

import os
import sys
import json
import base64
import time
import statistics
from PIL import Image
import io
import numpy as np

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

def create_test_images(num_images=10, width=224, height=224):
    """Create multiple test images for benchmarking."""
    images = []
    
    colors = ['red', 'green', 'blue', 'yellow', 'purple', 'orange', 'pink', 'brown', 'gray', 'black']
    
    for i in range(num_images):
        color = colors[i % len(colors)]
        img = Image.new('RGB', (width, height), color=color)
        
        # Convert to base64
        buffer = io.BytesIO()
        img.save(buffer, format='JPEG')
        img_str = base64.b64encode(buffer.getvalue()).decode()
        
        images.append(f"data:image/jpeg;base64,{img_str}")
    
    return images

def benchmark_embedding_generation(service, images, num_runs=5):
    """Benchmark face embedding generation."""
    print("=== Embedding Generation Benchmark ===")
    
    times = []
    
    for run in range(num_runs):
        print(f"Run {run + 1}/{num_runs}")
        
        for i, image in enumerate(images):
            start_time = time.time()
            embedding = service.generate_embedding(image)
            end_time = time.time()
            
            if embedding:
                elapsed = end_time - start_time
                times.append(elapsed)
                print(f"  Image {i+1}: {elapsed:.3f}s")
            else:
                print(f"  Image {i+1}: Failed")
    
    if times:
        avg_time = statistics.mean(times)
        median_time = statistics.median(times)
        min_time = min(times)
        max_time = max(times)
        std_time = statistics.stdev(times) if len(times) > 1 else 0
        
        print(f"\nResults:")
        print(f"  - Average time: {avg_time:.3f}s")
        print(f"  - Median time: {median_time:.3f}s")
        print(f"  - Min time: {min_time:.3f}s")
        print(f"  - Max time: {max_time:.3f}s")
        print(f"  - Std deviation: {std_time:.3f}s")
        print(f"  - Total images: {len(times)}")
        
        return {
            'average': avg_time,
            'median': median_time,
            'min': min_time,
            'max': max_time,
            'std': std_time,
            'count': len(times)
        }
    
    return None

def benchmark_face_recognition(service, images, num_runs=5):
    """Benchmark face recognition."""
    print("\n=== Face Recognition Benchmark ===")
    
    times = []
    
    for run in range(num_runs):
        print(f"Run {run + 1}/{num_runs}")
        
        for i, image in enumerate(images):
            start_time = time.time()
            result = service.recognize_face(image)
            end_time = time.time()
            
            if result:
                elapsed = end_time - start_time
                times.append(elapsed)
                print(f"  Image {i+1}: {elapsed:.3f}s")
            else:
                print(f"  Image {i+1}: Failed")
    
    if times:
        avg_time = statistics.mean(times)
        median_time = statistics.median(times)
        min_time = min(times)
        max_time = max(times)
        std_time = statistics.stdev(times) if len(times) > 1 else 0
        
        print(f"\nResults:")
        print(f"  - Average time: {avg_time:.3f}s")
        print(f"  - Median time: {median_time:.3f}s")
        print(f"  - Min time: {min_time:.3f}s")
        print(f"  - Max time: {max_time:.3f}s")
        print(f"  - Std deviation: {std_time:.3f}s")
        print(f"  - Total images: {len(times)}")
        
        return {
            'average': avg_time,
            'median': median_time,
            'min': min_time,
            'max': max_time,
            'std': std_time,
            'count': len(times)
        }
    
    return None

def benchmark_attendance_processing(service, images, num_runs=5):
    """Benchmark attendance processing."""
    print("\n=== Attendance Processing Benchmark ===")
    
    times = []
    
    for run in range(num_runs):
        print(f"Run {run + 1}/{num_runs}")
        
        for i, image in enumerate(images):
            start_time = time.time()
            result = service.process_attendance(image)
            end_time = time.time()
            
            if result:
                elapsed = end_time - start_time
                times.append(elapsed)
                print(f"  Image {i+1}: {elapsed:.3f}s")
            else:
                print(f"  Image {i+1}: Failed")
    
    if times:
        avg_time = statistics.mean(times)
        median_time = statistics.median(times)
        min_time = min(times)
        max_time = max(times)
        std_time = statistics.stdev(times) if len(times) > 1 else 0
        
        print(f"\nResults:")
        print(f"  - Average time: {avg_time:.3f}s")
        print(f"  - Median time: {median_time:.3f}s")
        print(f"  - Min time: {min_time:.3f}s")
        print(f"  - Max time: {max_time:.3f}s")
        print(f"  - Std deviation: {std_time:.3f}s")
        print(f"  - Total images: {len(times)}")
        
        return {
            'average': avg_time,
            'median': median_time,
            'min': min_time,
            'max': max_time,
            'std': std_time,
            'count': len(times)
        }
    
    return None

def benchmark_cli_interface(images, num_runs=3):
    """Benchmark CLI interface."""
    print("\n=== CLI Interface Benchmark ===")
    
    times = []
    
    for run in range(num_runs):
        print(f"Run {run + 1}/{num_runs}")
        
        for i, image in enumerate(images):
            test_data = {
                'action': 'generate_embedding',
                'image': image,
                'threshold': 1.0
            }
            
            start_time = time.time()
            
            try:
                import subprocess
                result = subprocess.run([
                    'python', 'facenet_cli.py', 
                    json.dumps(test_data)
                ], capture_output=True, text=True, timeout=30)
                
                end_time = time.time()
                
                if result.returncode == 0:
                    elapsed = end_time - start_time
                    times.append(elapsed)
                    print(f"  Image {i+1}: {elapsed:.3f}s")
                else:
                    print(f"  Image {i+1}: Failed")
            except Exception as e:
                print(f"  Image {i+1}: Error - {e}")
    
    if times:
        avg_time = statistics.mean(times)
        median_time = statistics.median(times)
        min_time = min(times)
        max_time = max(times)
        std_time = statistics.stdev(times) if len(times) > 1 else 0
        
        print(f"\nResults:")
        print(f"  - Average time: {avg_time:.3f}s")
        print(f"  - Median time: {median_time:.3f}s")
        print(f"  - Min time: {min_time:.3f}s")
        print(f"  - Max time: {max_time:.3f}s")
        print(f"  - Std deviation: {std_time:.3f}s")
        print(f"  - Total images: {len(times)}")
        
        return {
            'average': avg_time,
            'median': median_time,
            'min': min_time,
            'max': max_time,
            'std': std_time,
            'count': len(times)
        }
    
    return None

def benchmark_memory_usage():
    """Benchmark memory usage."""
    print("\n=== Memory Usage Benchmark ===")
    
    try:
        import psutil
        import gc
        
        # Get initial memory
        process = psutil.Process()
        initial_memory = process.memory_info().rss / 1024 / 1024  # MB
        
        print(f"Initial memory usage: {initial_memory:.2f} MB")
        
        # Import and initialize FaceNet
        from facenet_service import FaceNetService
        service = FaceNetService()
        
        # Get memory after initialization
        init_memory = process.memory_info().rss / 1024 / 1024  # MB
        print(f"Memory after initialization: {init_memory:.2f} MB")
        print(f"Memory increase: {init_memory - initial_memory:.2f} MB")
        
        # Test with multiple images
        images = create_test_images(10)
        
        for i, image in enumerate(images):
            embedding = service.generate_embedding(image)
            
            if i % 5 == 0:  # Check memory every 5 images
                current_memory = process.memory_info().rss / 1024 / 1024  # MB
                print(f"Memory after {i+1} images: {current_memory:.2f} MB")
        
        # Final memory
        final_memory = process.memory_info().rss / 1024 / 1024  # MB
        print(f"Final memory usage: {final_memory:.2f} MB")
        print(f"Total memory increase: {final_memory - initial_memory:.2f} MB")
        
        # Cleanup
        del service
        gc.collect()
        
        cleanup_memory = process.memory_info().rss / 1024 / 1024  # MB
        print(f"Memory after cleanup: {cleanup_memory:.2f} MB")
        
        return {
            'initial': initial_memory,
            'after_init': init_memory,
            'final': final_memory,
            'cleanup': cleanup_memory
        }
        
    except ImportError:
        print("psutil not available, skipping memory benchmark")
        return None

def save_benchmark_results(results, filename='benchmark_results.json'):
    """Save benchmark results to file."""
    try:
        with open(filename, 'w') as f:
            json.dump(results, f, indent=2)
        print(f"\n✓ Benchmark results saved to {filename}")
    except Exception as e:
        print(f"✗ Error saving results: {e}")

def main():
    """Run all benchmarks."""
    print("FaceNet Performance Benchmark")
    print("=" * 50)
    
    # Configuration
    num_images = 10
    num_runs = 3
    
    # Create test images
    print(f"Creating {num_images} test images...")
    images = create_test_images(num_images)
    print("✓ Test images created")
    
    # Initialize service
    print("\nInitializing FaceNet service...")
    try:
        from facenet_service import FaceNetService
        service = FaceNetService()
        print("✓ FaceNet service initialized")
    except Exception as e:
        print(f"✗ Failed to initialize service: {e}")
        return
    
    # Run benchmarks
    results = {}
    
    # Embedding generation benchmark
    embedding_results = benchmark_embedding_generation(service, images, num_runs)
    if embedding_results:
        results['embedding_generation'] = embedding_results
    
    # Face recognition benchmark
    recognition_results = benchmark_face_recognition(service, images, num_runs)
    if recognition_results:
        results['face_recognition'] = recognition_results
    
    # Attendance processing benchmark
    attendance_results = benchmark_attendance_processing(service, images, num_runs)
    if attendance_results:
        results['attendance_processing'] = attendance_results
    
    # CLI interface benchmark
    cli_results = benchmark_cli_interface(images, num_runs)
    if cli_results:
        results['cli_interface'] = cli_results
    
    # Memory usage benchmark
    memory_results = benchmark_memory_usage()
    if memory_results:
        results['memory_usage'] = memory_results
    
    # Save results
    save_benchmark_results(results)
    
    print("\n" + "=" * 50)
    print("Benchmark completed!")
    
    # Summary
    if results:
        print("\nSummary:")
        for benchmark, data in results.items():
            if 'average' in data:
                print(f"  {benchmark}: {data['average']:.3f}s average")
            elif 'after_init' in data:
                print(f"  {benchmark}: {data['after_init']:.2f} MB after init")

if __name__ == '__main__':
    main()
