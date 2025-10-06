#!/usr/bin/env python3
"""
FaceNet Health Check

This script performs health checks on the FaceNet service.
"""

import os
import sys
import json
import time
import subprocess
import requests
from datetime import datetime

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetHealthCheck:
    """Health check for FaceNet service."""
    
    def __init__(self):
        """Initialize health check."""
        self.checks = []
        self.results = {}
    
    def add_check(self, name, check_func, critical=False):
        """Add a health check."""
        self.checks.append({
            'name': name,
            'function': check_func,
            'critical': critical
        })
    
    def run_checks(self):
        """Run all health checks."""
        print("FaceNet Health Check")
        print("=" * 50)
        print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print()
        
        all_passed = True
        critical_failed = False
        
        for check in self.checks:
            print(f"Running {check['name']}...")
            
            try:
                result = check['function']()
                if result['status'] == 'pass':
                    print(f"✓ {check['name']}: {result['message']}")
                else:
                    print(f"✗ {check['name']}: {result['message']}")
                    all_passed = False
                    
                    if check['critical']:
                        critical_failed = True
                
                self.results[check['name']] = result
                
            except Exception as e:
                print(f"✗ {check['name']}: Error - {str(e)}")
                self.results[check['name']] = {
                    'status': 'fail',
                    'message': f"Error: {str(e)}"
                }
                all_passed = False
                
                if check['critical']:
                    critical_failed = True
            
            print()
        
        # Summary
        print("=" * 50)
        if all_passed:
            print("✓ All health checks passed")
        elif critical_failed:
            print("✗ Critical health checks failed")
        else:
            print("⚠ Some health checks failed (non-critical)")
        
        return all_passed, critical_failed
    
    def check_python_version(self):
        """Check Python version."""
        version = sys.version_info
        if version.major >= 3 and version.minor >= 6:
            return {
                'status': 'pass',
                'message': f"Python {version.major}.{version.minor}.{version.micro}"
            }
        else:
            return {
                'status': 'fail',
                'message': f"Python {version.major}.{version.minor}.{version.micro} (requires 3.6+)"
            }
    
    def check_dependencies(self):
        """Check required dependencies."""
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
            except ImportError:
                missing_packages.append(package)
        
        if not missing_packages:
            return {
                'status': 'pass',
                'message': "All required packages available"
            }
        else:
            return {
                'status': 'fail',
                'message': f"Missing packages: {', '.join(missing_packages)}"
            }
    
    def check_facenet_models(self):
        """Check if FaceNet models are available."""
        model_paths = [
            'facenet-master/models/20180402-114759',
            'facenet-master/models/mtcnn_weights'
        ]
        
        missing_models = []
        for path in model_paths:
            if not os.path.exists(path):
                missing_models.append(path)
        
        if not missing_models:
            return {
                'status': 'pass',
                'message': "All FaceNet models available"
            }
        else:
            return {
                'status': 'fail',
                'message': f"Missing models: {', '.join(missing_models)}"
            }
    
    def check_facenet_service(self):
        """Check if FaceNet service can be initialized."""
        try:
            from facenet_service import FaceNetService
            service = FaceNetService()
            return {
                'status': 'pass',
                'message': "FaceNet service initialized successfully"
            }
        except Exception as e:
            return {
                'status': 'fail',
                'message': f"Failed to initialize service: {str(e)}"
            }
    
    def check_database_connection(self):
        """Check database connection."""
        try:
            from facenet_database import db
            if db.is_connected():
                return {
                    'status': 'pass',
                    'message': "Database connection successful"
                }
            else:
                return {
                    'status': 'fail',
                    'message': "Database connection failed"
                }
        except Exception as e:
            return {
                'status': 'fail',
                'message': f"Database error: {str(e)}"
            }
    
    def check_cli_interface(self):
        """Check CLI interface."""
        try:
            test_data = {
                'action': 'generate_embedding',
                'image': 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A'
            }
            
            result = subprocess.run([
                'python', 'facenet_cli.py', 
                json.dumps(test_data)
            ], capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0:
                response = json.loads(result.stdout)
                if response.get('success'):
                    return {
                        'status': 'pass',
                        'message': "CLI interface working"
                    }
                else:
                    return {
                        'status': 'fail',
                        'message': f"CLI error: {response.get('error', 'Unknown error')}"
                    }
            else:
                return {
                    'status': 'fail',
                    'message': f"CLI failed: {result.stderr}"
                }
        except Exception as e:
            return {
                'status': 'fail',
                'message': f"CLI error: {str(e)}"
            }
    
    def check_api_endpoint(self):
        """Check API endpoint."""
        try:
            test_data = {
                'action': 'generate_embedding',
                'image': 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A'
            }
            
            response = requests.post(
                'http://localhost/facenet_api.php',
                data=test_data,
                timeout=30
            )
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    return {
                        'status': 'pass',
                        'message': "API endpoint working"
                    }
                else:
                    return {
                        'status': 'fail',
                        'message': f"API error: {result.get('error', 'Unknown error')}"
                    }
            else:
                return {
                    'status': 'fail',
                    'message': f"API returned status {response.status_code}"
                }
        except Exception as e:
            return {
                'status': 'fail',
                'message': f"API error: {str(e)}"
            }
    
    def check_file_permissions(self):
        """Check file permissions."""
        required_files = [
            'facenet_service.py',
            'facenet_cli.py',
            'facenet_api.php'
        ]
        
        permission_issues = []
        for file in required_files:
            if os.path.exists(file):
                if not os.access(file, os.R_OK):
                    permission_issues.append(f"{file} not readable")
                if not os.access(file, os.X_OK):
                    permission_issues.append(f"{file} not executable")
            else:
                permission_issues.append(f"{file} not found")
        
        if not permission_issues:
            return {
                'status': 'pass',
                'message': "All required files have correct permissions"
            }
        else:
            return {
                'status': 'fail',
                'message': f"Permission issues: {', '.join(permission_issues)}"
            }
    
    def check_system_resources(self):
        """Check system resources."""
        try:
            import psutil
            
            # Check CPU
            cpu_percent = psutil.cpu_percent(interval=1)
            
            # Check memory
            memory = psutil.virtual_memory()
            
            # Check disk
            disk = psutil.disk_usage('/')
            
            issues = []
            if cpu_percent > 90:
                issues.append(f"High CPU usage: {cpu_percent:.1f}%")
            if memory.percent > 90:
                issues.append(f"High memory usage: {memory.percent:.1f}%")
            if disk.percent > 90:
                issues.append(f"High disk usage: {disk.percent:.1f}%")
            
            if not issues:
                return {
                    'status': 'pass',
                    'message': f"CPU: {cpu_percent:.1f}%, Memory: {memory.percent:.1f}%, Disk: {disk.percent:.1f}%"
                }
            else:
                return {
                    'status': 'fail',
                    'message': f"Resource issues: {', '.join(issues)}"
                }
        except ImportError:
            return {
                'status': 'fail',
                'message': "psutil not available for resource monitoring"
            }
    
    def check_embedding_stats(self):
        """Check embedding statistics."""
        try:
            from facenet_database import db
            if db.is_connected():
                stats = db.get_embedding_stats()
                if stats:
                    return {
                        'status': 'pass',
                        'message': f"Embeddings: {stats.get('users_with_embeddings', 0)}/{stats.get('total_users', 0)} users"
                    }
                else:
                    return {
                        'status': 'fail',
                        'message': "Failed to get embedding statistics"
                    }
            else:
                return {
                    'status': 'fail',
                    'message': "Database not connected"
                }
        except Exception as e:
            return {
                'status': 'fail',
                'message': f"Embedding stats error: {str(e)}"
            }
    
    def save_results(self, filename='health_check_results.json'):
        """Save health check results to file."""
        try:
            with open(filename, 'w') as f:
                json.dump(self.results, f, indent=2)
            print(f"✓ Health check results saved to {filename}")
        except Exception as e:
            print(f"✗ Error saving results: {e}")

def main():
    """Main function."""
    health_check = FaceNetHealthCheck()
    
    # Add health checks
    health_check.add_check("Python Version", health_check.check_python_version, critical=True)
    health_check.add_check("Dependencies", health_check.check_dependencies, critical=True)
    health_check.add_check("FaceNet Models", health_check.check_facenet_models, critical=True)
    health_check.add_check("FaceNet Service", health_check.check_facenet_service, critical=True)
    health_check.add_check("Database Connection", health_check.check_database_connection, critical=True)
    health_check.add_check("CLI Interface", health_check.check_cli_interface, critical=True)
    health_check.add_check("API Endpoint", health_check.check_api_endpoint, critical=True)
    health_check.add_check("File Permissions", health_check.check_file_permissions, critical=True)
    health_check.add_check("System Resources", health_check.check_system_resources, critical=False)
    health_check.add_check("Embedding Statistics", health_check.check_embedding_stats, critical=False)
    
    # Run health checks
    all_passed, critical_failed = health_check.run_checks()
    
    # Save results
    health_check.save_results()
    
    # Exit with appropriate code
    if critical_failed:
        sys.exit(1)
    elif not all_passed:
        sys.exit(2)
    else:
        sys.exit(0)

if __name__ == '__main__':
    main()
