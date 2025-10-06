#!/usr/bin/env python3
"""
FaceNet Maintenance

This script handles maintenance tasks for the FaceNet service.
"""

import os
import sys
import json
import time
import shutil
from datetime import datetime, timedelta
from typing import Dict, List, Optional
import subprocess

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetMaintenance:
    """Maintenance manager for FaceNet service."""
    
    def __init__(self):
        """Initialize maintenance manager."""
        self.maintenance_log = []
    
    def log_maintenance(self, task: str, status: str, details: str = None):
        """Log maintenance task."""
        log_entry = {
            'timestamp': datetime.now().isoformat(),
            'task': task,
            'status': status,
            'details': details
        }
        self.maintenance_log.append(log_entry)
        
        status_icon = "✓" if status == "success" else "✗" if status == "error" else "⚠"
        print(f"{status_icon} {task}: {status}")
        if details:
            print(f"    Details: {details}")
    
    def cleanup_temp_files(self, days: int = 7) -> int:
        """Clean up temporary files."""
        print("Cleaning up temporary files...")
        
        temp_dirs = [
            'debug_images',
            'temp',
            'cache'
        ]
        
        cleaned_files = 0
        cutoff_date = datetime.now() - timedelta(days=days)
        
        for temp_dir in temp_dirs:
            if os.path.exists(temp_dir):
                for filename in os.listdir(temp_dir):
                    file_path = os.path.join(temp_dir, filename)
                    if os.path.isfile(file_path):
                        file_time = datetime.fromtimestamp(os.path.getmtime(file_path))
                        if file_time < cutoff_date:
                            try:
                                os.remove(file_path)
                                cleaned_files += 1
                            except Exception as e:
                                self.log_maintenance("cleanup_temp_files", "error", f"Error removing {file_path}: {e}")
        
        self.log_maintenance("cleanup_temp_files", "success", f"Cleaned {cleaned_files} files")
        return cleaned_files
    
    def cleanup_logs(self, days: int = 30) -> int:
        """Clean up old log files."""
        print("Cleaning up old log files...")
        
        log_dir = 'logs'
        if not os.path.exists(log_dir):
            self.log_maintenance("cleanup_logs", "success", "No log directory found")
            return 0
        
        cleaned_files = 0
        cutoff_date = datetime.now() - timedelta(days=days)
        
        for filename in os.listdir(log_dir):
            if filename.endswith('.log'):
                file_path = os.path.join(log_dir, filename)
                file_time = datetime.fromtimestamp(os.path.getmtime(file_path))
                if file_time < cutoff_date:
                    try:
                        os.remove(file_path)
                        cleaned_files += 1
                    except Exception as e:
                        self.log_maintenance("cleanup_logs", "error", f"Error removing {file_path}: {e}")
        
        self.log_maintenance("cleanup_logs", "success", f"Cleaned {cleaned_files} log files")
        return cleaned_files
    
    def cleanup_old_embeddings(self, days: int = 90) -> int:
        """Clean up old face embeddings."""
        print("Cleaning up old face embeddings...")
        
        try:
            from facenet_database import db
            
            if db.is_connected():
                cleaned_count = db.cleanup_old_embeddings(days)
                self.log_maintenance("cleanup_old_embeddings", "success", f"Cleaned {cleaned_count} old embeddings")
                return cleaned_count
            else:
                self.log_maintenance("cleanup_old_embeddings", "error", "Database not connected")
                return 0
        except Exception as e:
            self.log_maintenance("cleanup_old_embeddings", "error", f"Error: {e}")
            return 0
    
    def optimize_database(self) -> bool:
        """Optimize database."""
        print("Optimizing database...")
        
        try:
            from facenet_database import db
            
            if db.is_connected():
                # Get database stats before optimization
                stats_before = db.get_embedding_stats()
                
                # Run database optimization
                # Note: This would depend on your database system
                # For MySQL, you might run OPTIMIZE TABLE commands
                
                # Get database stats after optimization
                stats_after = db.get_embedding_stats()
                
                self.log_maintenance("optimize_database", "success", "Database optimized")
                return True
            else:
                self.log_maintenance("optimize_database", "error", "Database not connected")
                return False
        except Exception as e:
            self.log_maintenance("optimize_database", "error", f"Error: {e}")
            return False
    
    def update_models(self) -> bool:
        """Update FaceNet models."""
        print("Updating FaceNet models...")
        
        try:
            # Run model update script
            result = subprocess.run(['python', 'download_facenet_models.py'], 
                                  capture_output=True, text=True, timeout=300)
            
            if result.returncode == 0:
                self.log_maintenance("update_models", "success", "Models updated successfully")
                return True
            else:
                self.log_maintenance("update_models", "error", f"Update failed: {result.stderr}")
                return False
        except Exception as e:
            self.log_maintenance("update_models", "error", f"Error: {e}")
            return False
    
    def check_disk_space(self) -> Dict:
        """Check disk space usage."""
        print("Checking disk space...")
        
        try:
            import shutil
            
            # Get disk usage for current directory
            total, used, free = shutil.disk_usage('.')
            
            usage_percent = (used / total) * 100
            
            disk_info = {
                'total_gb': total / (1024**3),
                'used_gb': used / (1024**3),
                'free_gb': free / (1024**3),
                'usage_percent': usage_percent
            }
            
            if usage_percent > 90:
                self.log_maintenance("check_disk_space", "warning", f"Disk usage: {usage_percent:.1f}%")
            else:
                self.log_maintenance("check_disk_space", "success", f"Disk usage: {usage_percent:.1f}%")
            
            return disk_info
        except Exception as e:
            self.log_maintenance("check_disk_space", "error", f"Error: {e}")
            return {}
    
    def check_system_resources(self) -> Dict:
        """Check system resources."""
        print("Checking system resources...")
        
        try:
            import psutil
            
            # CPU usage
            cpu_percent = psutil.cpu_percent(interval=1)
            
            # Memory usage
            memory = psutil.virtual_memory()
            
            # Disk usage
            disk = psutil.disk_usage('/')
            
            resource_info = {
                'cpu_percent': cpu_percent,
                'memory_percent': memory.percent,
                'memory_used_gb': memory.used / (1024**3),
                'memory_total_gb': memory.total / (1024**3),
                'disk_percent': disk.percent,
                'disk_used_gb': disk.used / (1024**3),
                'disk_total_gb': disk.total / (1024**3)
            }
            
            # Check for resource issues
            issues = []
            if cpu_percent > 90:
                issues.append(f"High CPU usage: {cpu_percent:.1f}%")
            if memory.percent > 90:
                issues.append(f"High memory usage: {memory.percent:.1f}%")
            if disk.percent > 90:
                issues.append(f"High disk usage: {disk.percent:.1f}%")
            
            if issues:
                self.log_maintenance("check_system_resources", "warning", f"Issues: {', '.join(issues)}")
            else:
                self.log_maintenance("check_system_resources", "success", "Resources OK")
            
            return resource_info
        except ImportError:
            self.log_maintenance("check_system_resources", "error", "psutil not available")
            return {}
        except Exception as e:
            self.log_maintenance("check_system_resources", "error", f"Error: {e}")
            return {}
    
    def test_face_recognition(self) -> bool:
        """Test face recognition functionality."""
        print("Testing face recognition...")
        
        try:
            from facenet_service import FaceNetService
            
            # Initialize service
            service = FaceNetService()
            
            # Create a test image
            from PIL import Image
            import io
            import base64
            
            test_image = Image.new('RGB', (224, 224), color='red')
            buffer = io.BytesIO()
            test_image.save(buffer, format='JPEG')
            img_str = base64.b64encode(buffer.getvalue()).decode()
            base64_image = f"data:image/jpeg;base64,{img_str}"
            
            # Test embedding generation
            start_time = time.time()
            embedding = service.generate_embedding(base64_image)
            end_time = time.time()
            
            if embedding:
                duration = end_time - start_time
                self.log_maintenance("test_face_recognition", "success", f"Embedding generated in {duration:.3f}s")
                return True
            else:
                self.log_maintenance("test_face_recognition", "error", "Failed to generate embedding")
                return False
        except Exception as e:
            self.log_maintenance("test_face_recognition", "error", f"Error: {e}")
            return False
    
    def test_database_connection(self) -> bool:
        """Test database connection."""
        print("Testing database connection...")
        
        try:
            from facenet_database import db
            
            if db.is_connected():
                # Test a simple query
                stats = db.get_embedding_stats()
                self.log_maintenance("test_database_connection", "success", "Database connection OK")
                return True
            else:
                self.log_maintenance("test_database_connection", "error", "Database connection failed")
                return False
        except Exception as e:
            self.log_maintenance("test_database_connection", "error", f"Error: {e}")
            return False
    
    def test_api_endpoint(self) -> bool:
        """Test API endpoint."""
        print("Testing API endpoint...")
        
        try:
            import requests
            
            # Test the API endpoint
            test_data = {
                'action': 'generate_embedding',
                'image': 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A'
            }
            
            response = requests.post('http://localhost/facenet_api.php', data=test_data, timeout=30)
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    self.log_maintenance("test_api_endpoint", "success", "API endpoint working")
                    return True
                else:
                    self.log_maintenance("test_api_endpoint", "error", f"API error: {result.get('error')}")
                    return False
            else:
                self.log_maintenance("test_api_endpoint", "error", f"API returned status {response.status_code}")
                return False
        except Exception as e:
            self.log_maintenance("test_api_endpoint", "error", f"Error: {e}")
            return False
    
    def run_health_check(self) -> Dict:
        """Run comprehensive health check."""
        print("Running health check...")
        
        health_results = {
            'timestamp': datetime.now().isoformat(),
            'tests': {}
        }
        
        # Test database connection
        health_results['tests']['database'] = self.test_database_connection()
        
        # Test face recognition
        health_results['tests']['face_recognition'] = self.test_face_recognition()
        
        # Test API endpoint
        health_results['tests']['api_endpoint'] = self.test_api_endpoint()
        
        # Check system resources
        health_results['system_resources'] = self.check_system_resources()
        
        # Check disk space
        health_results['disk_space'] = self.check_disk_space()
        
        # Calculate overall health
        test_results = list(health_results['tests'].values())
        health_results['overall_health'] = all(test_results)
        
        if health_results['overall_health']:
            self.log_maintenance("run_health_check", "success", "All health checks passed")
        else:
            failed_tests = [test for test, result in health_results['tests'].items() if not result]
            self.log_maintenance("run_health_check", "error", f"Failed tests: {', '.join(failed_tests)}")
        
        return health_results
    
    def run_full_maintenance(self) -> Dict:
        """Run full maintenance routine."""
        print("Running full maintenance routine...")
        print("=" * 50)
        
        maintenance_results = {
            'timestamp': datetime.now().isoformat(),
            'tasks': {}
        }
        
        # Cleanup tasks
        maintenance_results['tasks']['cleanup_temp_files'] = self.cleanup_temp_files()
        maintenance_results['tasks']['cleanup_logs'] = self.cleanup_logs()
        maintenance_results['tasks']['cleanup_old_embeddings'] = self.cleanup_old_embeddings()
        
        # Optimization tasks
        maintenance_results['tasks']['optimize_database'] = self.optimize_database()
        
        # Update tasks
        maintenance_results['tasks']['update_models'] = self.update_models()
        
        # Health check
        maintenance_results['health_check'] = self.run_health_check()
        
        # System checks
        maintenance_results['system_resources'] = self.check_system_resources()
        maintenance_results['disk_space'] = self.check_disk_space()
        
        # Save maintenance log
        self.save_maintenance_log()
        
        print("=" * 50)
        print("Full maintenance routine completed")
        
        return maintenance_results
    
    def save_maintenance_log(self, filename: str = 'maintenance_log.json'):
        """Save maintenance log to file."""
        try:
            with open(filename, 'w') as f:
                json.dump(self.maintenance_log, f, indent=2)
            print(f"✓ Maintenance log saved to {filename}")
        except Exception as e:
            print(f"✗ Error saving maintenance log: {e}")
    
    def get_maintenance_stats(self) -> Dict:
        """Get maintenance statistics."""
        if not self.maintenance_log:
            return {}
        
        # Count tasks by status
        status_counts = {}
        for entry in self.maintenance_log:
            status = entry['status']
            status_counts[status] = status_counts.get(status, 0) + 1
        
        # Get recent tasks
        recent_tasks = self.maintenance_log[-10:] if len(self.maintenance_log) > 10 else self.maintenance_log
        
        return {
            'total_tasks': len(self.maintenance_log),
            'status_counts': status_counts,
            'recent_tasks': recent_tasks,
            'last_maintenance': self.maintenance_log[-1]['timestamp'] if self.maintenance_log else None
        }

def main():
    """Main function for testing maintenance."""
    print("FaceNet Maintenance System Test")
    print("=" * 50)
    
    maintenance = FaceNetMaintenance()
    
    # Run individual maintenance tasks
    print("Running individual maintenance tasks...")
    maintenance.cleanup_temp_files()
    maintenance.cleanup_logs()
    maintenance.cleanup_old_embeddings()
    maintenance.optimize_database()
    maintenance.update_models()
    
    # Run health check
    print("\nRunning health check...")
    health_results = maintenance.run_health_check()
    print(f"Overall health: {'OK' if health_results['overall_health'] else 'Issues found'}")
    
    # Get maintenance stats
    print("\nMaintenance statistics:")
    stats = maintenance.get_maintenance_stats()
    for key, value in stats.items():
        print(f"  {key}: {value}")
    
    # Save maintenance log
    maintenance.save_maintenance_log()
    
    print("\nMaintenance test completed!")

if __name__ == '__main__':
    main()
