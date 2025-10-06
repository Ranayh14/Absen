#!/usr/bin/env python3
"""
FaceNet Utilities

This file contains utility functions for the FaceNet service.
"""

import os
import sys
import json
import base64
import numpy as np
from PIL import Image
import io
import hashlib
import time
from typing import List, Dict, Optional, Tuple

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

def validate_base64_image(base64_string: str) -> bool:
    """Validate if a string is a valid base64 encoded image."""
    try:
        # Remove data URL prefix if present
        if ',' in base64_string:
            base64_string = base64_string.split(',')[1]
        
        # Decode base64
        image_data = base64.b64decode(base64_string)
        
        # Try to open as image
        image = Image.open(io.BytesIO(image_data))
        image.verify()
        
        return True
    except Exception:
        return False

def base64_to_image(base64_string: str) -> Optional[Image.Image]:
    """Convert base64 string to PIL Image."""
    try:
        # Remove data URL prefix if present
        if ',' in base64_string:
            base64_string = base64_string.split(',')[1]
        
        # Decode base64
        image_data = base64.b64decode(base64_string)
        
        # Convert to PIL Image
        image = Image.open(io.BytesIO(image_data))
        
        # Convert to RGB if necessary
        if image.mode != 'RGB':
            image = image.convert('RGB')
        
        return image
    except Exception as e:
        print(f"Error converting base64 to image: {e}", file=sys.stderr)
        return None

def image_to_base64(image: Image.Image, format: str = 'JPEG', quality: int = 95) -> str:
    """Convert PIL Image to base64 string."""
    try:
        buffer = io.BytesIO()
        image.save(buffer, format=format, quality=quality)
        img_str = base64.b64encode(buffer.getvalue()).decode()
        return f"data:image/{format.lower()};base64,{img_str}"
    except Exception as e:
        print(f"Error converting image to base64: {e}", file=sys.stderr)
        return ""

def resize_image(image: Image.Image, size: Tuple[int, int]) -> Image.Image:
    """Resize image while maintaining aspect ratio."""
    try:
        return image.resize(size, Image.Resampling.LANCZOS)
    except Exception as e:
        print(f"Error resizing image: {e}", file=sys.stderr)
        return image

def crop_face(image: Image.Image, bbox: List[int], margin: int = 32) -> Image.Image:
    """Crop face from image with margin."""
    try:
        x, y, w, h = bbox
        
        # Add margin
        x = max(0, x - margin)
        y = max(0, y - margin)
        w = min(image.width - x, w + 2 * margin)
        h = min(image.height - y, h + 2 * margin)
        
        # Crop image
        cropped = image.crop((x, y, x + w, y + h))
        
        return cropped
    except Exception as e:
        print(f"Error cropping face: {e}", file=sys.stderr)
        return image

def calculate_embedding_distance(embedding1: np.ndarray, embedding2: np.ndarray, method: str = 'euclidean') -> float:
    """Calculate distance between two face embeddings."""
    try:
        if method == 'euclidean':
            return float(np.linalg.norm(embedding1 - embedding2))
        elif method == 'cosine':
            # Calculate cosine similarity
            dot_product = np.dot(embedding1, embedding2)
            norm1 = np.linalg.norm(embedding1)
            norm2 = np.linalg.norm(embedding2)
            cosine_similarity = dot_product / (norm1 * norm2)
            # Convert to distance (1 - similarity)
            return float(1.0 - cosine_similarity)
        else:
            raise ValueError(f"Unknown distance method: {method}")
    except Exception as e:
        print(f"Error calculating embedding distance: {e}", file=sys.stderr)
        return float('inf')

def normalize_embedding(embedding: np.ndarray) -> np.ndarray:
    """Normalize face embedding to unit length."""
    try:
        norm = np.linalg.norm(embedding)
        if norm == 0:
            return embedding
        return embedding / norm
    except Exception as e:
        print(f"Error normalizing embedding: {e}", file=sys.stderr)
        return embedding

def generate_image_hash(image: Image.Image) -> str:
    """Generate hash for image to detect duplicates."""
    try:
        # Convert to bytes
        buffer = io.BytesIO()
        image.save(buffer, format='JPEG')
        image_bytes = buffer.getvalue()
        
        # Generate hash
        return hashlib.md5(image_bytes).hexdigest()
    except Exception as e:
        print(f"Error generating image hash: {e}", file=sys.stderr)
        return ""

def save_debug_image(image: Image.Image, filename: str, debug_path: str = 'debug_images') -> bool:
    """Save image for debugging purposes."""
    try:
        os.makedirs(debug_path, exist_ok=True)
        filepath = os.path.join(debug_path, filename)
        image.save(filepath)
        return True
    except Exception as e:
        print(f"Error saving debug image: {e}", file=sys.stderr)
        return False

def load_embeddings_from_file(filepath: str) -> Dict[str, np.ndarray]:
    """Load face embeddings from JSON file."""
    try:
        with open(filepath, 'r') as f:
            data = json.load(f)
        
        embeddings = {}
        for user_id, embedding_list in data.items():
            embeddings[user_id] = np.array(embedding_list)
        
        return embeddings
    except Exception as e:
        print(f"Error loading embeddings from file: {e}", file=sys.stderr)
        return {}

def save_embeddings_to_file(embeddings: Dict[str, np.ndarray], filepath: str) -> bool:
    """Save face embeddings to JSON file."""
    try:
        # Convert numpy arrays to lists
        data = {}
        for user_id, embedding in embeddings.items():
            data[user_id] = embedding.tolist()
        
        with open(filepath, 'w') as f:
            json.dump(data, f, indent=2)
        
        return True
    except Exception as e:
        print(f"Error saving embeddings to file: {e}", file=sys.stderr)
        return False

def format_embedding(embedding: np.ndarray) -> List[float]:
    """Format embedding for JSON serialization."""
    try:
        return embedding.tolist()
    except Exception as e:
        print(f"Error formatting embedding: {e}", file=sys.stderr)
        return []

def parse_embedding(embedding_list: List[float]) -> np.ndarray:
    """Parse embedding from JSON format."""
    try:
        return np.array(embedding_list)
    except Exception as e:
        print(f"Error parsing embedding: {e}", file=sys.stderr)
        return np.array([])

def validate_embedding(embedding: np.ndarray) -> bool:
    """Validate if embedding is valid."""
    try:
        # Check if it's a numpy array
        if not isinstance(embedding, np.ndarray):
            return False
        
        # Check if it has the right shape (512 for FaceNet)
        if embedding.shape != (512,):
            return False
        
        # Check if it contains valid numbers
        if not np.all(np.isfinite(embedding)):
            return False
        
        return True
    except Exception:
        return False

def get_image_info(image: Image.Image) -> Dict[str, any]:
    """Get information about an image."""
    try:
        return {
            'size': image.size,
            'mode': image.mode,
            'format': image.format,
            'width': image.width,
            'height': image.height,
            'has_transparency': image.mode in ('RGBA', 'LA') or 'transparency' in image.info
        }
    except Exception as e:
        print(f"Error getting image info: {e}", file=sys.stderr)
        return {}

def create_thumbnail(image: Image.Image, size: Tuple[int, int] = (150, 150)) -> Image.Image:
    """Create thumbnail of image."""
    try:
        return image.copy().thumbnail(size, Image.Resampling.LANCZOS)
    except Exception as e:
        print(f"Error creating thumbnail: {e}", file=sys.stderr)
        return image

def measure_time(func):
    """Decorator to measure function execution time."""
    def wrapper(*args, **kwargs):
        start_time = time.time()
        result = func(*args, **kwargs)
        end_time = time.time()
        execution_time = end_time - start_time
        print(f"{func.__name__} executed in {execution_time:.3f} seconds")
        return result
    return wrapper

def log_performance(func):
    """Decorator to log function performance."""
    def wrapper(*args, **kwargs):
        start_time = time.time()
        result = func(*args, **kwargs)
        end_time = time.time()
        execution_time = end_time - start_time
        
        # Log to file
        log_entry = {
            'function': func.__name__,
            'execution_time': execution_time,
            'timestamp': time.time(),
            'args_count': len(args),
            'kwargs_count': len(kwargs)
        }
        
        try:
            with open('performance.log', 'a') as f:
                f.write(json.dumps(log_entry) + '\n')
        except Exception:
            pass  # Ignore logging errors
        
        return result
    return wrapper

# Cache implementation
class EmbeddingCache:
    """Simple cache for face embeddings."""
    
    def __init__(self, max_size: int = 1000, ttl: int = 3600):
        self.cache = {}
        self.max_size = max_size
        self.ttl = ttl
        self.timestamps = {}
    
    def get(self, key: str) -> Optional[np.ndarray]:
        """Get embedding from cache."""
        if key in self.cache:
            # Check if expired
            if time.time() - self.timestamps[key] > self.ttl:
                self.delete(key)
                return None
            return self.cache[key]
        return None
    
    def set(self, key: str, embedding: np.ndarray) -> None:
        """Set embedding in cache."""
        # Remove oldest entries if cache is full
        if len(self.cache) >= self.max_size:
            oldest_key = min(self.timestamps.keys(), key=lambda k: self.timestamps[k])
            self.delete(oldest_key)
        
        self.cache[key] = embedding
        self.timestamps[key] = time.time()
    
    def delete(self, key: str) -> None:
        """Delete embedding from cache."""
        if key in self.cache:
            del self.cache[key]
            del self.timestamps[key]
    
    def clear(self) -> None:
        """Clear all cache entries."""
        self.cache.clear()
        self.timestamps.clear()
    
    def size(self) -> int:
        """Get cache size."""
        return len(self.cache)

# Global cache instance
embedding_cache = EmbeddingCache()

if __name__ == '__main__':
    # Test utility functions
    print("Testing FaceNet utilities...")
    
    # Test base64 validation
    test_base64 = "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A"
    print(f"Base64 validation: {validate_base64_image(test_base64)}")
    
    # Test image creation
    test_image = Image.new('RGB', (224, 224), color='red')
    print(f"Image info: {get_image_info(test_image)}")
    
    # Test cache
    test_embedding = np.random.rand(512)
    embedding_cache.set('test_key', test_embedding)
    cached_embedding = embedding_cache.get('test_key')
    print(f"Cache test: {np.array_equal(test_embedding, cached_embedding)}")
    
    print("Utility tests completed!")
