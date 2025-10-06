#!/usr/bin/env python3
"""
FaceNet CLI Tool

This script provides a command-line interface for managing FaceNet service.
"""

import os
import sys
import json
import argparse
import time
from datetime import datetime
from typing import Dict, List, Optional

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetCLITool:
    """Command-line tool for FaceNet management."""
    
    def __init__(self):
        """Initialize CLI tool."""
        self.parser = argparse.ArgumentParser(description='FaceNet CLI Tool')
        self.setup_commands()
    
    def setup_commands(self):
        """Setup command-line arguments."""
        subparsers = self.parser.add_subparsers(dest='command', help='Available commands')
        
        # Status command
        status_parser = subparsers.add_parser('status', help='Show service status')
        
        # Health command
        health_parser = subparsers.add_parser('health', help='Run health check')
        
        # Test command
        test_parser = subparsers.add_parser('test', help='Test FaceNet functionality')
        test_parser.add_argument('--image', help='Test image file path')
        
        # Embedding command
        embedding_parser = subparsers.add_parser('embedding', help='Generate face embedding')
        embedding_parser.add_argument('--image', required=True, help='Image file path')
        embedding_parser.add_argument('--user-id', type=int, help='User ID for saving embedding')
        embedding_parser.add_argument('--save', action='store_true', help='Save embedding to database')
        
        # Recognition command
        recognition_parser = subparsers.add_parser('recognize', help='Recognize face')
        recognition_parser.add_argument('--image', required=True, help='Image file path')
        recognition_parser.add_argument('--threshold', type=float, default=1.0, help='Recognition threshold')
        
        # Database command
        db_parser = subparsers.add_parser('database', help='Database operations')
        db_subparsers = db_parser.add_subparsers(dest='db_action', help='Database actions')
        
        # Database stats
        db_subparsers.add_parser('stats', help='Show database statistics')
        
        # Database backup
        db_subparsers.add_parser('backup', help='Backup database')
        
        # Database restore
        restore_parser = db_subparsers.add_parser('restore', help='Restore database')
        restore_parser.add_argument('--file', required=True, help='Backup file path')
        
        # Maintenance command
        maintenance_parser = subparsers.add_parser('maintenance', help='Run maintenance tasks')
        maintenance_parser.add_argument('--full', action='store_true', help='Run full maintenance')
        maintenance_parser.add_argument('--cleanup', action='store_true', help='Clean up old files')
        maintenance_parser.add_argument('--optimize', action='store_true', help='Optimize database')
        
        # Backup command
        backup_parser = subparsers.add_parser('backup', help='Backup system')
        backup_parser.add_argument('--name', help='Backup name')
        backup_parser.add_argument('--include-models', action='store_true', help='Include models in backup')
        backup_parser.add_argument('--include-logs', action='store_true', help='Include logs in backup')
        
        # Restore command
        restore_parser = subparsers.add_parser('restore', help='Restore system')
        restore_parser.add_argument('--file', required=True, help='Backup file path')
        restore_parser.add_argument('--include-models', action='store_true', help='Restore models')
        restore_parser.add_argument('--include-logs', action='store_true', help='Restore logs')
        
        # Monitor command
        monitor_parser = subparsers.add_parser('monitor', help='Start monitoring')
        monitor_parser.add_argument('--duration', type=int, default=60, help='Monitoring duration in seconds')
        
        # Dashboard command
        dashboard_parser = subparsers.add_parser('dashboard', help='Start dashboard')
        dashboard_parser.add_argument('--port', type=int, default=8080, help='Dashboard port')
        
        # Install command
        install_parser = subparsers.add_parser('install', help='Install FaceNet')
        
        # Update command
        update_parser = subparsers.add_parser('update', help='Update FaceNet')
    
    def run(self):
        """Run the CLI tool."""
        args = self.parser.parse_args()
        
        if not args.command:
            self.parser.print_help()
            return
        
        try:
            if args.command == 'status':
                self.show_status()
            elif args.command == 'health':
                self.run_health_check()
            elif args.command == 'test':
                self.test_functionality(args)
            elif args.command == 'embedding':
                self.generate_embedding(args)
            elif args.command == 'recognize':
                self.recognize_face(args)
            elif args.command == 'database':
                self.database_operations(args)
            elif args.command == 'maintenance':
                self.run_maintenance(args)
            elif args.command == 'backup':
                self.create_backup(args)
            elif args.command == 'restore':
                self.restore_backup(args)
            elif args.command == 'monitor':
                self.start_monitoring(args)
            elif args.command == 'dashboard':
                self.start_dashboard(args)
            elif args.command == 'install':
                self.install_facenet()
            elif args.command == 'update':
                self.update_facenet()
            else:
                print(f"Unknown command: {args.command}")
        except Exception as e:
            print(f"Error: {e}")
            sys.exit(1)
    
    def show_status(self):
        """Show service status."""
        print("FaceNet Service Status")
        print("=" * 50)
        
        # Check Python version
        print(f"Python Version: {sys.version}")
        
        # Check dependencies
        print("\nDependencies:")
        required_packages = ['tensorflow', 'numpy', 'PIL', 'opencv-python', 'scipy', 'scikit-learn']
        for package in required_packages:
            try:
                __import__(package)
                print(f"  ✓ {package}")
            except ImportError:
                print(f"  ✗ {package}")
        
        # Check models
        print("\nModels:")
        model_paths = [
            'facenet-master/models/20180402-114759',
            'facenet-master/models/mtcnn_weights'
        ]
        for path in model_paths:
            if os.path.exists(path):
                print(f"  ✓ {path}")
            else:
                print(f"  ✗ {path}")
        
        # Check database
        print("\nDatabase:")
        try:
            from facenet_database import db
            if db.is_connected():
                print("  ✓ Database connected")
                stats = db.get_embedding_stats()
                print(f"  - Total users: {stats.get('total_users', 0)}")
                print(f"  - Users with embeddings: {stats.get('users_with_embeddings', 0)}")
            else:
                print("  ✗ Database not connected")
        except Exception as e:
            print(f"  ✗ Database error: {e}")
        
        # Check service
        print("\nService:")
        try:
            from facenet_service import FaceNetService
            service = FaceNetService()
            print("  ✓ FaceNet service initialized")
        except Exception as e:
            print(f"  ✗ Service error: {e}")
    
    def run_health_check(self):
        """Run health check."""
        print("Running FaceNet Health Check...")
        print("=" * 50)
        
        try:
            from facenet_health_check import FaceNetHealthCheck
            
            health_check = FaceNetHealthCheck()
            all_passed, critical_failed = health_check.run_checks()
            
            if all_passed:
                print("✓ All health checks passed")
                sys.exit(0)
            elif critical_failed:
                print("✗ Critical health checks failed")
                sys.exit(1)
            else:
                print("⚠ Some health checks failed (non-critical)")
                sys.exit(2)
        except Exception as e:
            print(f"Error running health check: {e}")
            sys.exit(1)
    
    def test_functionality(self, args):
        """Test FaceNet functionality."""
        print("Testing FaceNet Functionality...")
        print("=" * 50)
        
        try:
            from facenet_service import FaceNetService
            
            service = FaceNetService()
            print("✓ Service initialized")
            
            # Create test image
            from PIL import Image
            import io
            import base64
            
            test_image = Image.new('RGB', (224, 224), color='red')
            buffer = io.BytesIO()
            test_image.save(buffer, format='JPEG')
            img_str = base64.b64encode(buffer.getvalue()).decode()
            base64_image = f"data:image/jpeg;base64,{img_str}"
            
            # Test embedding generation
            print("Testing embedding generation...")
            start_time = time.time()
            embedding = service.generate_embedding(base64_image)
            end_time = time.time()
            
            if embedding:
                print(f"✓ Embedding generated in {end_time - start_time:.3f}s")
            else:
                print("✗ Embedding generation failed")
            
            # Test face recognition
            print("Testing face recognition...")
            start_time = time.time()
            result = service.recognize_face(base64_image)
            end_time = time.time()
            
            if result:
                print(f"✓ Face recognition completed in {end_time - start_time:.3f}s")
                print(f"  Result: {result}")
            else:
                print("✗ Face recognition failed")
            
            # Test attendance processing
            print("Testing attendance processing...")
            start_time = time.time()
            result = service.process_attendance(base64_image)
            end_time = time.time()
            
            if result:
                print(f"✓ Attendance processing completed in {end_time - start_time:.3f}s")
                print(f"  Result: {result}")
            else:
                print("✗ Attendance processing failed")
            
        except Exception as e:
            print(f"Error testing functionality: {e}")
    
    def generate_embedding(self, args):
        """Generate face embedding."""
        print(f"Generating face embedding from {args.image}...")
        
        try:
            # Load image
            from PIL import Image
            import io
            import base64
            
            with open(args.image, 'rb') as f:
                image_data = f.read()
            
            base64_image = f"data:image/jpeg;base64,{base64.b64encode(image_data).decode()}"
            
            # Generate embedding
            from facenet_service import FaceNetService
            service = FaceNetService()
            
            start_time = time.time()
            embedding = service.generate_embedding(base64_image)
            end_time = time.time()
            
            if embedding:
                print(f"✓ Embedding generated in {end_time - start_time:.3f}s")
                print(f"  Dimensions: {len(embedding)}")
                
                if args.save and args.user_id:
                    success = service.save_embedding_to_database(args.user_id, embedding)
                    if success:
                        print(f"✓ Embedding saved for user {args.user_id}")
                    else:
                        print(f"✗ Failed to save embedding for user {args.user_id}")
            else:
                print("✗ Embedding generation failed")
        except Exception as e:
            print(f"Error generating embedding: {e}")
    
    def recognize_face(self, args):
        """Recognize face."""
        print(f"Recognizing face from {args.image}...")
        
        try:
            # Load image
            from PIL import Image
            import io
            import base64
            
            with open(args.image, 'rb') as f:
                image_data = f.read()
            
            base64_image = f"data:image/jpeg;base64,{base64.b64encode(image_data).decode()}"
            
            # Recognize face
            from facenet_service import FaceNetService
            service = FaceNetService()
            
            start_time = time.time()
            result = service.recognize_face(base64_image, args.threshold)
            end_time = time.time()
            
            if result:
                print(f"✓ Face recognition completed in {end_time - start_time:.3f}s")
                print(f"  Result: {json.dumps(result, indent=2)}")
            else:
                print("✗ Face recognition failed")
        except Exception as e:
            print(f"Error recognizing face: {e}")
    
    def database_operations(self, args):
        """Handle database operations."""
        if args.db_action == 'stats':
            self.show_database_stats()
        elif args.db_action == 'backup':
            self.backup_database()
        elif args.db_action == 'restore':
            self.restore_database(args)
        else:
            print("Unknown database action")
    
    def show_database_stats(self):
        """Show database statistics."""
        print("Database Statistics")
        print("=" * 50)
        
        try:
            from facenet_database import db
            
            if db.is_connected():
                stats = db.get_embedding_stats()
                print(f"Total users: {stats.get('total_users', 0)}")
                print(f"Users with embeddings: {stats.get('users_with_embeddings', 0)}")
                print(f"Users without embeddings: {stats.get('users_without_embeddings', 0)}")
                print(f"Recent updates: {stats.get('recent_updates', 0)}")
                print(f"Coverage: {stats.get('coverage_percentage', 0):.1f}%")
            else:
                print("Database not connected")
        except Exception as e:
            print(f"Error getting database stats: {e}")
    
    def backup_database(self):
        """Backup database."""
        print("Backing up database...")
        
        try:
            from facenet_database import db
            
            if db.is_connected():
                backup_file = f"database_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
                success = db.backup_embeddings(backup_file)
                if success:
                    print(f"✓ Database backed up to {backup_file}")
                else:
                    print("✗ Database backup failed")
            else:
                print("Database not connected")
        except Exception as e:
            print(f"Error backing up database: {e}")
    
    def restore_database(self, args):
        """Restore database."""
        print(f"Restoring database from {args.file}...")
        
        try:
            from facenet_database import db
            
            if db.is_connected():
                success = db.restore_embeddings(args.file)
                if success:
                    print(f"✓ Database restored from {args.file}")
                else:
                    print("✗ Database restore failed")
            else:
                print("Database not connected")
        except Exception as e:
            print(f"Error restoring database: {e}")
    
    def run_maintenance(self, args):
        """Run maintenance tasks."""
        print("Running maintenance tasks...")
        print("=" * 50)
        
        try:
            from facenet_maintenance import FaceNetMaintenance
            
            maintenance = FaceNetMaintenance()
            
            if args.full:
                results = maintenance.run_full_maintenance()
                print("Full maintenance completed")
            else:
                if args.cleanup:
                    maintenance.cleanup_temp_files()
                    maintenance.cleanup_logs()
                    maintenance.cleanup_old_embeddings()
                
                if args.optimize:
                    maintenance.optimize_database()
                
                if not args.cleanup and not args.optimize:
                    # Run default maintenance
                    maintenance.cleanup_temp_files()
                    maintenance.cleanup_logs()
                    maintenance.optimize_database()
            
        except Exception as e:
            print(f"Error running maintenance: {e}")
    
    def create_backup(self, args):
        """Create system backup."""
        print("Creating system backup...")
        
        try:
            from facenet_backup import FaceNetBackup
            
            backup_manager = FaceNetBackup()
            backup_path = backup_manager.create_backup(
                args.name,
                include_models=args.include_models,
                include_logs=args.include_logs
            )
            print(f"✓ Backup created: {backup_path}")
        except Exception as e:
            print(f"Error creating backup: {e}")
    
    def restore_backup(self, args):
        """Restore system backup."""
        print(f"Restoring system from {args.file}...")
        
        try:
            from facenet_backup import FaceNetBackup
            
            backup_manager = FaceNetBackup()
            success = backup_manager.restore_backup(
                args.file,
                restore_models=args.include_models,
                restore_logs=args.include_logs
            )
            if success:
                print(f"✓ System restored from {args.file}")
            else:
                print(f"✗ System restore failed")
        except Exception as e:
            print(f"Error restoring backup: {e}")
    
    def start_monitoring(self, args):
        """Start monitoring."""
        print(f"Starting monitoring for {args.duration} seconds...")
        
        try:
            from facenet_monitor import monitor_facenet_service
            
            # Start monitoring in a separate thread
            import threading
            monitor_thread = threading.Thread(target=monitor_facenet_service)
            monitor_thread.daemon = True
            monitor_thread.start()
            
            # Wait for specified duration
            time.sleep(args.duration)
            print("Monitoring completed")
        except Exception as e:
            print(f"Error starting monitoring: {e}")
    
    def start_dashboard(self, args):
        """Start dashboard."""
        print(f"Starting dashboard on port {args.port}...")
        
        try:
            from facenet_dashboard import FaceNetDashboard
            
            dashboard = FaceNetDashboard(args.port)
            dashboard.start()
        except Exception as e:
            print(f"Error starting dashboard: {e}")
    
    def install_facenet(self):
        """Install FaceNet."""
        print("Installing FaceNet...")
        
        try:
            from install_facenet import main as install_main
            install_main()
        except Exception as e:
            print(f"Error installing FaceNet: {e}")
    
    def update_facenet(self):
        """Update FaceNet."""
        print("Updating FaceNet...")
        
        try:
            from facenet_maintenance import FaceNetMaintenance
            
            maintenance = FaceNetMaintenance()
            success = maintenance.update_models()
            if success:
                print("✓ FaceNet updated successfully")
            else:
                print("✗ FaceNet update failed")
        except Exception as e:
            print(f"Error updating FaceNet: {e}")

def main():
    """Main function."""
    cli_tool = FaceNetCLITool()
    cli_tool.run()

if __name__ == '__main__':
    main()
