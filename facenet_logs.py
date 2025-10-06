#!/usr/bin/env python3
"""
FaceNet Logs

This script manages logging for the FaceNet service.
"""

import os
import sys
import json
import time
import logging
from datetime import datetime, timedelta
from logging.handlers import RotatingFileHandler
import traceback

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetLogger:
    """Logger for FaceNet service."""
    
    def __init__(self, log_dir='logs', max_file_size=10*1024*1024, backup_count=5):
        """Initialize logger."""
        self.log_dir = log_dir
        self.max_file_size = max_file_size
        self.backup_count = backup_count
        
        # Create log directory
        os.makedirs(log_dir, exist_ok=True)
        
        # Setup loggers
        self.setup_loggers()
    
    def setup_loggers(self):
        """Setup loggers for different components."""
        # Main logger
        self.main_logger = self.create_logger(
            'facenet_main',
            os.path.join(self.log_dir, 'facenet_main.log'),
            level=logging.INFO
        )
        
        # Error logger
        self.error_logger = self.create_logger(
            'facenet_error',
            os.path.join(self.log_dir, 'facenet_error.log'),
            level=logging.ERROR
        )
        
        # Performance logger
        self.performance_logger = self.create_logger(
            'facenet_performance',
            os.path.join(self.log_dir, 'facenet_performance.log'),
            level=logging.INFO
        )
        
        # Request logger
        self.request_logger = self.create_logger(
            'facenet_request',
            os.path.join(self.log_dir, 'facenet_request.log'),
            level=logging.INFO
        )
        
        # Debug logger
        self.debug_logger = self.create_logger(
            'facenet_debug',
            os.path.join(self.log_dir, 'facenet_debug.log'),
            level=logging.DEBUG
        )
    
    def create_logger(self, name, log_file, level=logging.INFO):
        """Create a logger with file rotation."""
        logger = logging.getLogger(name)
        logger.setLevel(level)
        
        # Remove existing handlers
        for handler in logger.handlers[:]:
            logger.removeHandler(handler)
        
        # Create rotating file handler
        handler = RotatingFileHandler(
            log_file,
            maxBytes=self.max_file_size,
            backupCount=self.backup_count
        )
        
        # Create formatter
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        handler.setFormatter(formatter)
        
        # Add handler to logger
        logger.addHandler(handler)
        
        return logger
    
    def log_info(self, message, component='main'):
        """Log info message."""
        if component == 'main':
            self.main_logger.info(message)
        elif component == 'performance':
            self.performance_logger.info(message)
        elif component == 'request':
            self.request_logger.info(message)
        elif component == 'debug':
            self.debug_logger.info(message)
    
    def log_error(self, message, exception=None, component='main'):
        """Log error message."""
        if exception:
            message += f" - Exception: {str(exception)}"
            message += f" - Traceback: {traceback.format_exc()}"
        
        self.error_logger.error(message)
        
        if component == 'main':
            self.main_logger.error(message)
        elif component == 'performance':
            self.performance_logger.error(message)
        elif component == 'request':
            self.request_logger.error(message)
        elif component == 'debug':
            self.debug_logger.error(message)
    
    def log_warning(self, message, component='main'):
        """Log warning message."""
        if component == 'main':
            self.main_logger.warning(message)
        elif component == 'performance':
            self.performance_logger.warning(message)
        elif component == 'request':
            self.request_logger.warning(message)
        elif component == 'debug':
            self.debug_logger.warning(message)
    
    def log_debug(self, message, component='debug'):
        """Log debug message."""
        self.debug_logger.debug(message)
    
    def log_performance(self, operation, duration, success=True, details=None):
        """Log performance metrics."""
        message = f"Operation: {operation}, Duration: {duration:.3f}s, Success: {success}"
        if details:
            message += f", Details: {details}"
        
        self.performance_logger.info(message)
    
    def log_request(self, request_type, duration, success=True, user_id=None, error=None):
        """Log request details."""
        message = f"Request: {request_type}, Duration: {duration:.3f}s, Success: {success}"
        if user_id:
            message += f", User: {user_id}"
        if error:
            message += f", Error: {error}"
        
        self.request_logger.info(message)
    
    def log_embedding_generation(self, user_id, duration, success=True, error=None):
        """Log embedding generation."""
        message = f"Embedding Generation - User: {user_id}, Duration: {duration:.3f}s, Success: {success}"
        if error:
            message += f", Error: {error}"
        
        self.log_info(message, 'request')
        self.log_performance('embedding_generation', duration, success, f"user_id={user_id}")
    
    def log_face_recognition(self, duration, success=True, user_id=None, confidence=None, error=None):
        """Log face recognition."""
        message = f"Face Recognition - Duration: {duration:.3f}s, Success: {success}"
        if user_id:
            message += f", User: {user_id}"
        if confidence:
            message += f", Confidence: {confidence:.3f}"
        if error:
            message += f", Error: {error}"
        
        self.log_info(message, 'request')
        self.log_performance('face_recognition', duration, success, f"user_id={user_id}, confidence={confidence}")
    
    def log_attendance_processing(self, duration, success=True, user_id=None, error=None):
        """Log attendance processing."""
        message = f"Attendance Processing - Duration: {duration:.3f}s, Success: {success}"
        if user_id:
            message += f", User: {user_id}"
        if error:
            message += f", Error: {error}"
        
        self.log_info(message, 'request')
        self.log_performance('attendance_processing', duration, success, f"user_id={user_id}")
    
    def log_database_operation(self, operation, duration, success=True, error=None):
        """Log database operations."""
        message = f"Database Operation: {operation}, Duration: {duration:.3f}s, Success: {success}"
        if error:
            message += f", Error: {error}"
        
        self.log_info(message, 'main')
        self.log_performance(f"database_{operation}", duration, success)
    
    def log_system_event(self, event, details=None):
        """Log system events."""
        message = f"System Event: {event}"
        if details:
            message += f", Details: {details}"
        
        self.log_info(message, 'main')
    
    def log_security_event(self, event, user_id=None, ip_address=None, details=None):
        """Log security events."""
        message = f"Security Event: {event}"
        if user_id:
            message += f", User: {user_id}"
        if ip_address:
            message += f", IP: {ip_address}"
        if details:
            message += f", Details: {details}"
        
        self.log_info(message, 'main')
        self.error_logger.warning(message)  # Security events are also logged as warnings
    
    def get_log_stats(self, days=7):
        """Get log statistics for the last N days."""
        stats = {
            'total_logs': 0,
            'error_logs': 0,
            'warning_logs': 0,
            'info_logs': 0,
            'debug_logs': 0,
            'performance_logs': 0,
            'request_logs': 0
        }
        
        cutoff_date = datetime.now() - timedelta(days=days)
        
        # Count logs in each file
        log_files = [
            ('facenet_main.log', 'main'),
            ('facenet_error.log', 'error'),
            ('facenet_performance.log', 'performance'),
            ('facenet_request.log', 'request'),
            ('facenet_debug.log', 'debug')
        ]
        
        for log_file, log_type in log_files:
            file_path = os.path.join(self.log_dir, log_file)
            if os.path.exists(file_path):
                try:
                    with open(file_path, 'r') as f:
                        for line in f:
                            if line.strip():
                                stats['total_logs'] += 1
                                stats[f'{log_type}_logs'] += 1
                                
                                # Parse log level
                                if 'ERROR' in line:
                                    stats['error_logs'] += 1
                                elif 'WARNING' in line:
                                    stats['warning_logs'] += 1
                                elif 'INFO' in line:
                                    stats['info_logs'] += 1
                                elif 'DEBUG' in line:
                                    stats['debug_logs'] += 1
                except Exception as e:
                    self.log_error(f"Error reading log file {log_file}: {e}")
        
        return stats
    
    def cleanup_old_logs(self, days=30):
        """Clean up old log files."""
        cutoff_date = datetime.now() - timedelta(days=days)
        cleaned_files = []
        
        for filename in os.listdir(self.log_dir):
            if filename.endswith('.log'):
                file_path = os.path.join(self.log_dir, filename)
                file_time = datetime.fromtimestamp(os.path.getmtime(file_path))
                
                if file_time < cutoff_date:
                    try:
                        os.remove(file_path)
                        cleaned_files.append(filename)
                    except Exception as e:
                        self.log_error(f"Error removing old log file {filename}: {e}")
        
        if cleaned_files:
            self.log_info(f"Cleaned up old log files: {', '.join(cleaned_files)}")
        
        return cleaned_files
    
    def export_logs(self, output_file, days=7, log_types=None):
        """Export logs to a file."""
        if log_types is None:
            log_types = ['main', 'error', 'performance', 'request', 'debug']
        
        cutoff_date = datetime.now() - timedelta(days=days)
        exported_logs = []
        
        for log_type in log_types:
            log_file = os.path.join(self.log_dir, f'facenet_{log_type}.log')
            if os.path.exists(log_file):
                try:
                    with open(log_file, 'r') as f:
                        for line in f:
                            if line.strip():
                                # Parse timestamp and filter by date
                                try:
                                    timestamp_str = line.split(' - ')[0]
                                    timestamp = datetime.strptime(timestamp_str, '%Y-%m-%d %H:%M:%S,%f')
                                    if timestamp >= cutoff_date:
                                        exported_logs.append(line.strip())
                                except:
                                    # If timestamp parsing fails, include the line anyway
                                    exported_logs.append(line.strip())
                except Exception as e:
                    self.log_error(f"Error reading log file {log_file}: {e}")
        
        # Sort logs by timestamp
        exported_logs.sort()
        
        # Write to output file
        try:
            with open(output_file, 'w') as f:
                for log in exported_logs:
                    f.write(log + '\n')
            
            self.log_info(f"Exported {len(exported_logs)} log entries to {output_file}")
            return len(exported_logs)
        except Exception as e:
            self.log_error(f"Error writing export file {output_file}: {e}")
            return 0

# Global logger instance
logger = FaceNetLogger()

def get_logger():
    """Get the global logger instance."""
    return logger

def log_info(message, component='main'):
    """Log info message using global logger."""
    logger.log_info(message, component)

def log_error(message, exception=None, component='main'):
    """Log error message using global logger."""
    logger.log_error(message, exception, component)

def log_warning(message, component='main'):
    """Log warning message using global logger."""
    logger.log_warning(message, component)

def log_debug(message, component='debug'):
    """Log debug message using global logger."""
    logger.log_debug(message, component)

def log_performance(operation, duration, success=True, details=None):
    """Log performance metrics using global logger."""
    logger.log_performance(operation, duration, success, details)

def log_request(request_type, duration, success=True, user_id=None, error=None):
    """Log request details using global logger."""
    logger.log_request(request_type, duration, success, user_id, error)

def log_embedding_generation(user_id, duration, success=True, error=None):
    """Log embedding generation using global logger."""
    logger.log_embedding_generation(user_id, duration, success, error)

def log_face_recognition(duration, success=True, user_id=None, confidence=None, error=None):
    """Log face recognition using global logger."""
    logger.log_face_recognition(duration, success, user_id, confidence, error)

def log_attendance_processing(duration, success=True, user_id=None, error=None):
    """Log attendance processing using global logger."""
    logger.log_attendance_processing(duration, success, user_id, error)

def log_database_operation(operation, duration, success=True, error=None):
    """Log database operations using global logger."""
    logger.log_database_operation(operation, duration, success, error)

def log_system_event(event, details=None):
    """Log system events using global logger."""
    logger.log_system_event(event, details)

def log_security_event(event, user_id=None, ip_address=None, details=None):
    """Log security events using global logger."""
    logger.log_security_event(event, user_id, ip_address, details)

def main():
    """Main function for testing logging."""
    print("FaceNet Logging System Test")
    print("=" * 50)
    
    # Test different log types
    log_info("Testing info logging")
    log_warning("Testing warning logging")
    log_error("Testing error logging")
    log_debug("Testing debug logging")
    
    # Test performance logging
    log_performance("test_operation", 1.234, True, "test details")
    
    # Test request logging
    log_request("test_request", 0.567, True, "user123")
    
    # Test specific logging functions
    log_embedding_generation("user123", 0.890, True)
    log_face_recognition(1.123, True, "user123", 0.95)
    log_attendance_processing(1.456, True, "user123")
    log_database_operation("select", 0.234, True)
    log_system_event("system_startup", "FaceNet service started")
    log_security_event("login_attempt", "user123", "192.168.1.1", "successful")
    
    # Get log stats
    stats = logger.get_log_stats(1)
    print(f"\nLog Statistics (Last 1 day):")
    for key, value in stats.items():
        print(f"  {key}: {value}")
    
    # Export logs
    exported_count = logger.export_logs('test_export.log', 1)
    print(f"\nExported {exported_count} log entries to test_export.log")
    
    print("\nLogging test completed!")

if __name__ == '__main__':
    main()
