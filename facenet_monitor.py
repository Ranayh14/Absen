#!/usr/bin/env python3
"""
FaceNet Monitor

This script monitors the FaceNet service and provides real-time statistics.
"""

import os
import sys
import json
import time
import psutil
import threading
from datetime import datetime, timedelta
from collections import deque, defaultdict

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetMonitor:
    """Monitor for FaceNet service."""
    
    def __init__(self, max_history=1000):
        """Initialize the monitor."""
        self.max_history = max_history
        self.stats = {
            'requests': deque(maxlen=max_history),
            'embeddings': deque(maxlen=max_history),
            'recognitions': deque(maxlen=max_history),
            'attendance': deque(maxlen=max_history),
            'errors': deque(maxlen=max_history)
        }
        self.counters = defaultdict(int)
        self.start_time = time.time()
        self.running = False
        
    def start(self):
        """Start monitoring."""
        self.running = True
        print("FaceNet Monitor started")
        print("Press Ctrl+C to stop")
        
        try:
            while self.running:
                self.update_stats()
                self.display_stats()
                time.sleep(5)  # Update every 5 seconds
        except KeyboardInterrupt:
            self.stop()
    
    def stop(self):
        """Stop monitoring."""
        self.running = False
        print("\nFaceNet Monitor stopped")
    
    def update_stats(self):
        """Update monitoring statistics."""
        # Get system stats
        cpu_percent = psutil.cpu_percent()
        memory = psutil.virtual_memory()
        disk = psutil.disk_usage('/')
        
        # Get process stats
        process = psutil.Process()
        process_memory = process.memory_info().rss / 1024 / 1024  # MB
        
        # Update stats
        current_time = time.time()
        self.stats['system'] = {
            'timestamp': current_time,
            'cpu_percent': cpu_percent,
            'memory_percent': memory.percent,
            'memory_used': memory.used / 1024 / 1024,  # MB
            'memory_total': memory.total / 1024 / 1024,  # MB
            'disk_percent': disk.percent,
            'disk_used': disk.used / 1024 / 1024 / 1024,  # GB
            'disk_total': disk.total / 1024 / 1024 / 1024,  # GB
            'process_memory': process_memory
        }
    
    def log_request(self, request_type, duration, success=True, error=None):
        """Log a request."""
        current_time = time.time()
        
        request_data = {
            'timestamp': current_time,
            'type': request_type,
            'duration': duration,
            'success': success,
            'error': error
        }
        
        self.stats['requests'].append(request_data)
        self.counters[f'{request_type}_total'] += 1
        
        if success:
            self.counters[f'{request_type}_success'] += 1
        else:
            self.counters[f'{request_type}_error'] += 1
            if error:
                self.stats['errors'].append({
                    'timestamp': current_time,
                    'type': request_type,
                    'error': error
                })
    
    def log_embedding(self, duration, success=True, error=None):
        """Log an embedding generation."""
        self.log_request('embedding', duration, success, error)
        self.stats['embeddings'].append({
            'timestamp': time.time(),
            'duration': duration,
            'success': success,
            'error': error
        })
    
    def log_recognition(self, duration, success=True, error=None):
        """Log a face recognition."""
        self.log_request('recognition', duration, success, error)
        self.stats['recognitions'].append({
            'timestamp': time.time(),
            'duration': duration,
            'success': success,
            'error': error
        })
    
    def log_attendance(self, duration, success=True, error=None):
        """Log an attendance processing."""
        self.log_request('attendance', duration, success, error)
        self.stats['attendance'].append({
            'timestamp': time.time(),
            'duration': duration,
            'success': success,
            'error': error
        })
    
    def get_uptime(self):
        """Get service uptime."""
        return time.time() - self.start_time
    
    def get_request_stats(self, request_type, time_window=300):
        """Get request statistics for a time window."""
        current_time = time.time()
        cutoff_time = current_time - time_window
        
        requests = [r for r in self.stats['requests'] if r['timestamp'] >= cutoff_time and r['type'] == request_type]
        
        if not requests:
            return None
        
        durations = [r['duration'] for r in requests]
        successes = [r for r in requests if r['success']]
        errors = [r for r in requests if not r['success']]
        
        return {
            'total': len(requests),
            'success': len(successes),
            'errors': len(errors),
            'success_rate': len(successes) / len(requests) * 100,
            'avg_duration': sum(durations) / len(durations),
            'min_duration': min(durations),
            'max_duration': max(durations)
        }
    
    def get_error_stats(self, time_window=300):
        """Get error statistics for a time window."""
        current_time = time.time()
        cutoff_time = current_time - time_window
        
        errors = [e for e in self.stats['errors'] if e['timestamp'] >= cutoff_time]
        
        if not errors:
            return None
        
        error_types = defaultdict(int)
        for error in errors:
            error_types[error['type']] += 1
        
        return {
            'total': len(errors),
            'by_type': dict(error_types)
        }
    
    def display_stats(self):
        """Display current statistics."""
        # Clear screen
        os.system('clear' if os.name == 'posix' else 'cls')
        
        print("FaceNet Service Monitor")
        print("=" * 50)
        print(f"Uptime: {self.get_uptime():.0f} seconds")
        print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print()
        
        # System stats
        if 'system' in self.stats:
            sys_stats = self.stats['system']
            print("System Resources:")
            print(f"  CPU: {sys_stats['cpu_percent']:.1f}%")
            print(f"  Memory: {sys_stats['memory_percent']:.1f}% ({sys_stats['memory_used']:.0f}MB / {sys_stats['memory_total']:.0f}MB)")
            print(f"  Disk: {sys_stats['disk_percent']:.1f}% ({sys_stats['disk_used']:.1f}GB / {sys_stats['disk_total']:.1f}GB)")
            print(f"  Process Memory: {sys_stats['process_memory']:.0f}MB")
            print()
        
        # Request stats (last 5 minutes)
        print("Request Statistics (Last 5 minutes):")
        for request_type in ['embedding', 'recognition', 'attendance']:
            stats = self.get_request_stats(request_type, 300)
            if stats:
                print(f"  {request_type.capitalize()}:")
                print(f"    Total: {stats['total']}")
                print(f"    Success: {stats['success']} ({stats['success_rate']:.1f}%)")
                print(f"    Errors: {stats['errors']}")
                print(f"    Avg Duration: {stats['avg_duration']:.3f}s")
                print(f"    Min/Max: {stats['min_duration']:.3f}s / {stats['max_duration']:.3f}s")
            else:
                print(f"  {request_type.capitalize()}: No requests")
        print()
        
        # Error stats
        error_stats = self.get_error_stats(300)
        if error_stats:
            print("Error Statistics (Last 5 minutes):")
            print(f"  Total Errors: {error_stats['total']}")
            for error_type, count in error_stats['by_type'].items():
                print(f"    {error_type}: {count}")
        else:
            print("Error Statistics (Last 5 minutes): No errors")
        print()
        
        # Total counters
        print("Total Counters:")
        for key, value in self.counters.items():
            print(f"  {key}: {value}")
        print()
        
        # Recent requests
        print("Recent Requests (Last 10):")
        recent_requests = list(self.stats['requests'])[-10:]
        for req in recent_requests:
            timestamp = datetime.fromtimestamp(req['timestamp']).strftime('%H:%M:%S')
            status = "✓" if req['success'] else "✗"
            print(f"  {timestamp} {status} {req['type']} ({req['duration']:.3f}s)")
        print()
        
        print("Press Ctrl+C to stop monitoring")

def monitor_facenet_service():
    """Monitor FaceNet service."""
    monitor = FaceNetMonitor()
    
    # Start monitoring in a separate thread
    monitor_thread = threading.Thread(target=monitor.start)
    monitor_thread.daemon = True
    monitor_thread.start()
    
    # Simulate some requests for testing
    def simulate_requests():
        import random
        while monitor.running:
            time.sleep(random.uniform(1, 5))
            
            # Simulate embedding generation
            duration = random.uniform(0.1, 2.0)
            success = random.random() > 0.1  # 90% success rate
            error = "Simulated error" if not success else None
            monitor.log_embedding(duration, success, error)
            
            # Simulate face recognition
            duration = random.uniform(0.2, 3.0)
            success = random.random() > 0.05  # 95% success rate
            error = "Simulated error" if not success else None
            monitor.log_recognition(duration, success, error)
            
            # Simulate attendance processing
            duration = random.uniform(0.3, 4.0)
            success = random.random() > 0.02  # 98% success rate
            error = "Simulated error" if not success else None
            monitor.log_attendance(duration, success, error)
    
    # Start simulation in a separate thread
    simulation_thread = threading.Thread(target=simulate_requests)
    simulation_thread.daemon = True
    simulation_thread.start()
    
    try:
        monitor.start()
    except KeyboardInterrupt:
        monitor.stop()

def main():
    """Main function."""
    print("FaceNet Service Monitor")
    print("=" * 50)
    
    # Check if psutil is available
    try:
        import psutil
    except ImportError:
        print("psutil is required for monitoring. Install it with: pip install psutil")
        return
    
    # Start monitoring
    monitor_facenet_service()

if __name__ == '__main__':
    main()
