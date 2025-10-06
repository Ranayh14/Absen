#!/usr/bin/env python3
"""
FaceNet Backup

This script handles backup and restore operations for FaceNet data.
"""

import os
import sys
import json
import time
import shutil
import zipfile
from datetime import datetime, timedelta
from typing import Dict, List, Optional
import subprocess

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetBackup:
    """Backup manager for FaceNet data."""
    
    def __init__(self, backup_dir='backups'):
        """Initialize backup manager."""
        self.backup_dir = backup_dir
        self.ensure_backup_dir()
    
    def ensure_backup_dir(self):
        """Ensure backup directory exists."""
        os.makedirs(self.backup_dir, exist_ok=True)
    
    def create_backup(self, backup_name: str = None, include_models: bool = True, include_logs: bool = True) -> str:
        """Create a backup of FaceNet data."""
        if backup_name is None:
            backup_name = f"facenet_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
        
        backup_path = os.path.join(self.backup_dir, f"{backup_name}.zip")
        
        print(f"Creating backup: {backup_name}")
        
        with zipfile.ZipFile(backup_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            # Backup database
            self.backup_database(zipf)
            
            # Backup configuration files
            self.backup_config_files(zipf)
            
            # Backup models if requested
            if include_models:
                self.backup_models(zipf)
            
            # Backup logs if requested
            if include_logs:
                self.backup_logs(zipf)
            
            # Backup Python files
            self.backup_python_files(zipf)
            
            # Create backup metadata
            self.create_backup_metadata(zipf, backup_name, include_models, include_logs)
        
        print(f"✓ Backup created: {backup_path}")
        return backup_path
    
    def backup_database(self, zipf: zipfile.ZipFile):
        """Backup database data."""
        try:
            from facenet_database import db
            
            if db.is_connected():
                # Get all embeddings
                embeddings = db.get_all_embeddings()
                
                # Save embeddings to JSON
                embeddings_data = {}
                for user_id, data in embeddings.items():
                    embeddings_data[user_id] = {
                        'nim': data['nim'],
                        'nama': data['nama'],
                        'embedding': data['embedding'].tolist()
                    }
                
                # Add to zip
                zipf.writestr('database/embeddings.json', json.dumps(embeddings_data, indent=2))
                
                # Get embedding stats
                stats = db.get_embedding_stats()
                zipf.writestr('database/stats.json', json.dumps(stats, indent=2))
                
                print("✓ Database data backed up")
            else:
                print("⚠ Database not connected, skipping database backup")
        except Exception as e:
            print(f"✗ Error backing up database: {e}")
    
    def backup_config_files(self, zipf: zipfile.ZipFile):
        """Backup configuration files."""
        config_files = [
            'facenet_config.py',
            'security_config.json',
            'requirements.txt'
        ]
        
        for config_file in config_files:
            if os.path.exists(config_file):
                zipf.write(config_file, f'config/{config_file}')
                print(f"✓ Backed up {config_file}")
    
    def backup_models(self, zipf: zipfile.ZipFile):
        """Backup FaceNet models."""
        model_dirs = [
            'facenet-master/models',
            'facenet-master/data'
        ]
        
        for model_dir in model_dirs:
            if os.path.exists(model_dir):
                for root, dirs, files in os.walk(model_dir):
                    for file in files:
                        file_path = os.path.join(root, file)
                        arcname = os.path.relpath(file_path, '.')
                        zipf.write(file_path, arcname)
                print(f"✓ Backed up {model_dir}")
    
    def backup_logs(self, zipf: zipfile.ZipFile):
        """Backup log files."""
        log_dir = 'logs'
        if os.path.exists(log_dir):
            for root, dirs, files in os.walk(log_dir):
                for file in files:
                    if file.endswith('.log'):
                        file_path = os.path.join(root, file)
                        arcname = os.path.relpath(file_path, '.')
                        zipf.write(file_path, arcname)
            print(f"✓ Backed up {log_dir}")
    
    def backup_python_files(self, zipf: zipfile.ZipFile):
        """Backup Python files."""
        python_files = [
            'facenet_service.py',
            'facenet_cli.py',
            'facenet_api.php',
            'facenet_database.py',
            'facenet_utils.py',
            'facenet_security.py',
            'facenet_logs.py',
            'facenet_monitor.py',
            'facenet_health_check.py',
            'facenet_benchmark.py',
            'facenet_demo.py',
            'install_facenet.py',
            'setup_facenet.py',
            'download_facenet_models.py',
            'test_facenet.py'
        ]
        
        for python_file in python_files:
            if os.path.exists(python_file):
                zipf.write(python_file, f'python/{python_file}')
                print(f"✓ Backed up {python_file}")
    
    def create_backup_metadata(self, zipf: zipfile.ZipFile, backup_name: str, include_models: bool, include_logs: bool):
        """Create backup metadata."""
        metadata = {
            'backup_name': backup_name,
            'created_at': datetime.now().isoformat(),
            'include_models': include_models,
            'include_logs': include_logs,
            'version': '1.0',
            'description': 'FaceNet system backup'
        }
        
        zipf.writestr('backup_metadata.json', json.dumps(metadata, indent=2))
    
    def restore_backup(self, backup_path: str, restore_models: bool = True, restore_logs: bool = True) -> bool:
        """Restore from a backup."""
        if not os.path.exists(backup_path):
            print(f"✗ Backup file not found: {backup_path}")
            return False
        
        print(f"Restoring from backup: {backup_path}")
        
        try:
            with zipfile.ZipFile(backup_path, 'r') as zipf:
                # Read backup metadata
                metadata = self.read_backup_metadata(zipf)
                if metadata:
                    print(f"Backup created: {metadata.get('created_at', 'Unknown')}")
                    print(f"Includes models: {metadata.get('include_models', False)}")
                    print(f"Includes logs: {metadata.get('include_logs', False)}")
                
                # Restore database
                self.restore_database(zipf)
                
                # Restore configuration files
                self.restore_config_files(zipf)
                
                # Restore models if requested
                if restore_models:
                    self.restore_models(zipf)
                
                # Restore logs if requested
                if restore_logs:
                    self.restore_logs(zipf)
                
                # Restore Python files
                self.restore_python_files(zipf)
            
            print("✓ Backup restored successfully")
            return True
        except Exception as e:
            print(f"✗ Error restoring backup: {e}")
            return False
    
    def read_backup_metadata(self, zipf: zipfile.ZipFile) -> Optional[Dict]:
        """Read backup metadata."""
        try:
            metadata_str = zipf.read('backup_metadata.json').decode('utf-8')
            return json.loads(metadata_str)
        except Exception:
            return None
    
    def restore_database(self, zipf: zipfile.ZipFile):
        """Restore database data."""
        try:
            from facenet_database import db
            
            if db.is_connected():
                # Restore embeddings
                if 'database/embeddings.json' in zipf.namelist():
                    embeddings_str = zipf.read('database/embeddings.json').decode('utf-8')
                    embeddings_data = json.loads(embeddings_str)
                    
                    restored_count = 0
                    for user_id, data in embeddings_data.items():
                        import numpy as np
                        embedding = np.array(data['embedding'])
                        if db.save_user_embedding(int(user_id), embedding):
                            restored_count += 1
                    
                    print(f"✓ Restored {restored_count} embeddings")
                else:
                    print("⚠ No embeddings found in backup")
            else:
                print("⚠ Database not connected, skipping database restore")
        except Exception as e:
            print(f"✗ Error restoring database: {e}")
    
    def restore_config_files(self, zipf: zipfile.ZipFile):
        """Restore configuration files."""
        config_files = [
            'facenet_config.py',
            'security_config.json',
            'requirements.txt'
        ]
        
        for config_file in config_files:
            zip_path = f'config/{config_file}'
            if zip_path in zipf.namelist():
                zipf.extract(zip_path, '.')
                # Move from config/ subdirectory
                if os.path.exists(zip_path):
                    shutil.move(zip_path, config_file)
                    os.rmdir('config')
                print(f"✓ Restored {config_file}")
    
    def restore_models(self, zipf: zipfile.ZipFile):
        """Restore FaceNet models."""
        model_files = [name for name in zipf.namelist() if name.startswith('facenet-master/')]
        
        if model_files:
            for model_file in model_files:
                zipf.extract(model_file, '.')
            print(f"✓ Restored {len(model_files)} model files")
        else:
            print("⚠ No model files found in backup")
    
    def restore_logs(self, zipf: zipfile.ZipFile):
        """Restore log files."""
        log_files = [name for name in zipf.namelist() if name.startswith('logs/')]
        
        if log_files:
            for log_file in log_files:
                zipf.extract(log_file, '.')
            print(f"✓ Restored {len(log_files)} log files")
        else:
            print("⚠ No log files found in backup")
    
    def restore_python_files(self, zipf: zipfile.ZipFile):
        """Restore Python files."""
        python_files = [name for name in zipf.namelist() if name.startswith('python/')]
        
        if python_files:
            for python_file in python_files:
                zipf.extract(python_file, '.')
                # Move from python/ subdirectory
                target_file = python_file.replace('python/', '')
                if os.path.exists(python_file):
                    shutil.move(python_file, target_file)
            os.rmdir('python')
            print(f"✓ Restored {len(python_files)} Python files")
        else:
            print("⚠ No Python files found in backup")
    
    def list_backups(self) -> List[Dict]:
        """List available backups."""
        backups = []
        
        for filename in os.listdir(self.backup_dir):
            if filename.endswith('.zip'):
                file_path = os.path.join(self.backup_dir, filename)
                file_stat = os.stat(file_path)
                
                backup_info = {
                    'filename': filename,
                    'path': file_path,
                    'size': file_stat.st_size,
                    'created_at': datetime.fromtimestamp(file_stat.st_ctime).isoformat(),
                    'modified_at': datetime.fromtimestamp(file_stat.st_mtime).isoformat()
                }
                
                # Try to read metadata
                try:
                    with zipfile.ZipFile(file_path, 'r') as zipf:
                        metadata = self.read_backup_metadata(zipf)
                        if metadata:
                            backup_info.update(metadata)
                except Exception:
                    pass
                
                backups.append(backup_info)
        
        # Sort by creation time (newest first)
        backups.sort(key=lambda x: x['created_at'], reverse=True)
        return backups
    
    def delete_backup(self, backup_name: str) -> bool:
        """Delete a backup."""
        backup_path = os.path.join(self.backup_dir, f"{backup_name}.zip")
        
        if not os.path.exists(backup_path):
            print(f"✗ Backup not found: {backup_name}")
            return False
        
        try:
            os.remove(backup_path)
            print(f"✓ Deleted backup: {backup_name}")
            return True
        except Exception as e:
            print(f"✗ Error deleting backup: {e}")
            return False
    
    def cleanup_old_backups(self, days: int = 30) -> int:
        """Clean up old backups."""
        cutoff_date = datetime.now() - timedelta(days=days)
        deleted_count = 0
        
        for filename in os.listdir(self.backup_dir):
            if filename.endswith('.zip'):
                file_path = os.path.join(self.backup_dir, filename)
                file_time = datetime.fromtimestamp(os.path.getmtime(file_path))
                
                if file_time < cutoff_date:
                    try:
                        os.remove(file_path)
                        deleted_count += 1
                        print(f"✓ Deleted old backup: {filename}")
                    except Exception as e:
                        print(f"✗ Error deleting {filename}: {e}")
        
        return deleted_count
    
    def get_backup_stats(self) -> Dict:
        """Get backup statistics."""
        backups = self.list_backups()
        
        total_size = sum(backup['size'] for backup in backups)
        total_count = len(backups)
        
        # Group by date
        by_date = {}
        for backup in backups:
            date = backup['created_at'][:10]  # YYYY-MM-DD
            if date not in by_date:
                by_date[date] = 0
            by_date[date] += 1
        
        return {
            'total_backups': total_count,
            'total_size': total_size,
            'total_size_mb': total_size / (1024 * 1024),
            'by_date': by_date,
            'oldest_backup': backups[-1]['created_at'] if backups else None,
            'newest_backup': backups[0]['created_at'] if backups else None
        }

def main():
    """Main function for testing backup functionality."""
    print("FaceNet Backup System Test")
    print("=" * 50)
    
    backup_manager = FaceNetBackup()
    
    # Create a test backup
    print("Creating test backup...")
    backup_path = backup_manager.create_backup("test_backup", include_models=False, include_logs=False)
    
    # List backups
    print("\nListing backups...")
    backups = backup_manager.list_backups()
    for backup in backups:
        print(f"  {backup['filename']} - {backup['size']} bytes - {backup['created_at']}")
    
    # Get backup stats
    print("\nBackup statistics:")
    stats = backup_manager.get_backup_stats()
    for key, value in stats.items():
        print(f"  {key}: {value}")
    
    # Test restore (dry run)
    print(f"\nTesting restore from {backup_path}...")
    # Note: In a real scenario, you might want to restore to a different location
    # restore_success = backup_manager.restore_backup(backup_path, restore_models=False, restore_logs=False)
    
    # Cleanup test backup
    print(f"\nCleaning up test backup...")
    backup_manager.delete_backup("test_backup")
    
    print("\nBackup test completed!")

if __name__ == '__main__':
    main()
