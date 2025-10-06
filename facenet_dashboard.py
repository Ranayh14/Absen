#!/usr/bin/env python3
"""
FaceNet Dashboard

This script provides a web-based dashboard for monitoring FaceNet service.
"""

import os
import sys
import json
import time
from datetime import datetime, timedelta
from typing import Dict, List, Optional
import threading
from http.server import HTTPServer, BaseHTTPRequestHandler
import urllib.parse

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetDashboard:
    """Web dashboard for FaceNet service."""
    
    def __init__(self, port: int = 8080):
        """Initialize dashboard."""
        self.port = port
        self.server = None
        self.running = False
        self.stats = {}
        self.update_interval = 5  # seconds
    
    def start(self):
        """Start the dashboard server."""
        try:
            self.server = HTTPServer(('localhost', self.port), DashboardHandler)
            self.server.dashboard = self
            self.running = True
            
            print(f"FaceNet Dashboard started on http://localhost:{self.port}")
            print("Press Ctrl+C to stop")
            
            # Start stats update thread
            stats_thread = threading.Thread(target=self.update_stats_loop)
            stats_thread.daemon = True
            stats_thread.start()
            
            # Start server
            self.server.serve_forever()
        except KeyboardInterrupt:
            self.stop()
        except Exception as e:
            print(f"Error starting dashboard: {e}")
    
    def stop(self):
        """Stop the dashboard server."""
        self.running = False
        if self.server:
            self.server.shutdown()
        print("Dashboard stopped")
    
    def update_stats_loop(self):
        """Update statistics in a loop."""
        while self.running:
            try:
                self.update_stats()
                time.sleep(self.update_interval)
            except Exception as e:
                print(f"Error updating stats: {e}")
                time.sleep(self.update_interval)
    
    def update_stats(self):
        """Update dashboard statistics."""
        try:
            # Get system stats
            self.stats['system'] = self.get_system_stats()
            
            # Get FaceNet stats
            self.stats['facenet'] = self.get_facenet_stats()
            
            # Get database stats
            self.stats['database'] = self.get_database_stats()
            
            # Get performance stats
            self.stats['performance'] = self.get_performance_stats()
            
            # Get security stats
            self.stats['security'] = self.get_security_stats()
            
            # Get maintenance stats
            self.stats['maintenance'] = self.get_maintenance_stats()
            
            self.stats['last_updated'] = datetime.now().isoformat()
        except Exception as e:
            print(f"Error updating stats: {e}")
    
    def get_system_stats(self) -> Dict:
        """Get system statistics."""
        try:
            import psutil
            
            return {
                'cpu_percent': psutil.cpu_percent(interval=1),
                'memory_percent': psutil.virtual_memory().percent,
                'memory_used_gb': psutil.virtual_memory().used / (1024**3),
                'memory_total_gb': psutil.virtual_memory().total / (1024**3),
                'disk_percent': psutil.disk_usage('/').percent,
                'disk_used_gb': psutil.disk_usage('/').used / (1024**3),
                'disk_total_gb': psutil.disk_usage('/').total / (1024**3)
            }
        except ImportError:
            return {'error': 'psutil not available'}
        except Exception as e:
            return {'error': str(e)}
    
    def get_facenet_stats(self) -> Dict:
        """Get FaceNet statistics."""
        try:
            from facenet_database import db
            
            if db.is_connected():
                return db.get_embedding_stats()
            else:
                return {'error': 'Database not connected'}
        except Exception as e:
            return {'error': str(e)}
    
    def get_database_stats(self) -> Dict:
        """Get database statistics."""
        try:
            from facenet_database import db
            
            if db.is_connected():
                return {
                    'connected': True,
                    'embeddings_count': len(db.get_all_embeddings())
                }
            else:
                return {'connected': False}
        except Exception as e:
            return {'error': str(e)}
    
    def get_performance_stats(self) -> Dict:
        """Get performance statistics."""
        try:
            # This would typically read from performance logs
            return {
                'avg_embedding_time': 0.5,
                'avg_recognition_time': 1.2,
                'avg_attendance_time': 1.8,
                'total_requests': 0,
                'success_rate': 0.95
            }
        except Exception as e:
            return {'error': str(e)}
    
    def get_security_stats(self) -> Dict:
        """Get security statistics."""
        try:
            from facenet_security import security
            
            return security.get_security_stats()
        except Exception as e:
            return {'error': str(e)}
    
    def get_maintenance_stats(self) -> Dict:
        """Get maintenance statistics."""
        try:
            # This would typically read from maintenance logs
            return {
                'last_maintenance': None,
                'maintenance_status': 'OK',
                'backup_count': 0,
                'backup_size_mb': 0
            }
        except Exception as e:
            return {'error': str(e)}

class DashboardHandler(BaseHTTPRequestHandler):
    """HTTP request handler for dashboard."""
    
    def do_GET(self):
        """Handle GET requests."""
        try:
            if self.path == '/':
                self.serve_dashboard()
            elif self.path == '/api/stats':
                self.serve_stats()
            elif self.path == '/api/health':
                self.serve_health()
            else:
                self.send_error(404)
        except Exception as e:
            self.send_error(500, str(e))
    
    def serve_dashboard(self):
        """Serve the main dashboard page."""
        html = self.get_dashboard_html()
        
        self.send_response(200)
        self.send_header('Content-type', 'text/html')
        self.end_headers()
        self.wfile.write(html.encode())
    
    def serve_stats(self):
        """Serve statistics as JSON."""
        stats = self.server.dashboard.stats
        
        self.send_response(200)
        self.send_header('Content-type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps(stats, indent=2).encode())
    
    def serve_health(self):
        """Serve health check as JSON."""
        try:
            from facenet_health_check import FaceNetHealthCheck
            
            health_check = FaceNetHealthCheck()
            all_passed, critical_failed = health_check.run_checks()
            
            health_data = {
                'status': 'healthy' if all_passed else 'unhealthy',
                'critical_failed': critical_failed,
                'timestamp': datetime.now().isoformat()
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(health_data, indent=2).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def get_dashboard_html(self) -> str:
        """Get dashboard HTML."""
        return """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FaceNet Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .stat-label {
            font-weight: bold;
            color: #666;
        }
        .stat-value {
            color: #333;
        }
        .status-ok {
            color: #28a745;
        }
        .status-warning {
            color: #ffc107;
        }
        .status-error {
            color: #dc3545;
        }
        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        .refresh-btn:hover {
            background: #5a6fd8;
        }
        .last-updated {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FaceNet Dashboard</h1>
            <p>Real-time monitoring of FaceNet service</p>
        </div>
        
        <button class="refresh-btn" onclick="refreshStats()">Refresh Stats</button>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>System Resources</h3>
                <div id="system-stats">Loading...</div>
            </div>
            
            <div class="stat-card">
                <h3>FaceNet Service</h3>
                <div id="facenet-stats">Loading...</div>
            </div>
            
            <div class="stat-card">
                <h3>Database</h3>
                <div id="database-stats">Loading...</div>
            </div>
            
            <div class="stat-card">
                <h3>Performance</h3>
                <div id="performance-stats">Loading...</div>
            </div>
            
            <div class="stat-card">
                <h3>Security</h3>
                <div id="security-stats">Loading...</div>
            </div>
            
            <div class="stat-card">
                <h3>Maintenance</h3>
                <div id="maintenance-stats">Loading...</div>
            </div>
        </div>
        
        <div class="last-updated" id="last-updated">
            Last updated: Never
        </div>
    </div>
    
    <script>
        function refreshStats() {
            fetch('/api/stats')
                .then(response => response.json())
                .then(data => {
                    updateStats(data);
                })
                .catch(error => {
                    console.error('Error fetching stats:', error);
                });
        }
        
        function updateStats(data) {
            // Update system stats
            if (data.system && !data.system.error) {
                document.getElementById('system-stats').innerHTML = `
                    <div class="stat-item">
                        <span class="stat-label">CPU Usage:</span>
                        <span class="stat-value">${data.system.cpu_percent.toFixed(1)}%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Memory Usage:</span>
                        <span class="stat-value">${data.system.memory_percent.toFixed(1)}%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Disk Usage:</span>
                        <span class="stat-value">${data.system.disk_percent.toFixed(1)}%</span>
                    </div>
                `;
            } else {
                document.getElementById('system-stats').innerHTML = '<div class="status-error">Error loading system stats</div>';
            }
            
            // Update FaceNet stats
            if (data.facenet && !data.facenet.error) {
                document.getElementById('facenet-stats').innerHTML = `
                    <div class="stat-item">
                        <span class="stat-label">Total Users:</span>
                        <span class="stat-value">${data.facenet.total_users || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Users with Embeddings:</span>
                        <span class="stat-value">${data.facenet.users_with_embeddings || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Coverage:</span>
                        <span class="stat-value">${(data.facenet.coverage_percentage || 0).toFixed(1)}%</span>
                    </div>
                `;
            } else {
                document.getElementById('facenet-stats').innerHTML = '<div class="status-error">Error loading FaceNet stats</div>';
            }
            
            // Update database stats
            if (data.database && !data.database.error) {
                document.getElementById('database-stats').innerHTML = `
                    <div class="stat-item">
                        <span class="stat-label">Status:</span>
                        <span class="stat-value ${data.database.connected ? 'status-ok' : 'status-error'}">
                            ${data.database.connected ? 'Connected' : 'Disconnected'}
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Embeddings:</span>
                        <span class="stat-value">${data.database.embeddings_count || 0}</span>
                    </div>
                `;
            } else {
                document.getElementById('database-stats').innerHTML = '<div class="status-error">Error loading database stats</div>';
            }
            
            // Update performance stats
            if (data.performance && !data.performance.error) {
                document.getElementById('performance-stats').innerHTML = `
                    <div class="stat-item">
                        <span class="stat-label">Avg Embedding Time:</span>
                        <span class="stat-value">${data.performance.avg_embedding_time.toFixed(3)}s</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Avg Recognition Time:</span>
                        <span class="stat-value">${data.performance.avg_recognition_time.toFixed(3)}s</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Success Rate:</span>
                        <span class="stat-value">${(data.performance.success_rate * 100).toFixed(1)}%</span>
                    </div>
                `;
            } else {
                document.getElementById('performance-stats').innerHTML = '<div class="status-error">Error loading performance stats</div>';
            }
            
            // Update security stats
            if (data.security && !data.security.error) {
                document.getElementById('security-stats').innerHTML = `
                    <div class="stat-item">
                        <span class="stat-label">Active Sessions:</span>
                        <span class="stat-value">${data.security.active_sessions || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Blocked IPs:</span>
                        <span class="stat-value">${data.security.blocked_ips || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Rate Limited IPs:</span>
                        <span class="stat-value">${data.security.rate_limited_ips || 0}</span>
                    </div>
                `;
            } else {
                document.getElementById('security-stats').innerHTML = '<div class="status-error">Error loading security stats</div>';
            }
            
            // Update maintenance stats
            if (data.maintenance && !data.maintenance.error) {
                document.getElementById('maintenance-stats').innerHTML = `
                    <div class="stat-item">
                        <span class="stat-label">Status:</span>
                        <span class="stat-value status-ok">${data.maintenance.maintenance_status || 'OK'}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Backup Count:</span>
                        <span class="stat-value">${data.maintenance.backup_count || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Backup Size:</span>
                        <span class="stat-value">${data.maintenance.backup_size_mb || 0} MB</span>
                    </div>
                `;
            } else {
                document.getElementById('maintenance-stats').innerHTML = '<div class="status-error">Error loading maintenance stats</div>';
            }
            
            // Update last updated time
            if (data.last_updated) {
                document.getElementById('last-updated').textContent = 
                    `Last updated: ${new Date(data.last_updated).toLocaleString()}`;
            }
        }
        
        // Auto-refresh every 5 seconds
        setInterval(refreshStats, 5000);
        
        // Initial load
        refreshStats();
    </script>
</body>
</html>
        """

def main():
    """Main function."""
    print("FaceNet Dashboard")
    print("=" * 50)
    
    # Check if port is available
    import socket
    port = 8080
    
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.bind(('localhost', port))
    except OSError:
        print(f"Port {port} is already in use. Trying port {port + 1}")
        port += 1
    
    # Start dashboard
    dashboard = FaceNetDashboard(port)
    dashboard.start()

if __name__ == '__main__':
    main()
