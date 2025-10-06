#!/usr/bin/env python3
"""
FaceNet Web Interface

This script provides a web-based interface for FaceNet management.
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
import base64
from PIL import Image
import io

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetWebInterface:
    """Web interface for FaceNet management."""
    
    def __init__(self, port: int = 8081):
        """Initialize web interface."""
        self.port = port
        self.server = None
        self.running = False
    
    def start(self):
        """Start the web interface server."""
        try:
            self.server = HTTPServer(('localhost', self.port), WebInterfaceHandler)
            self.server.web_interface = self
            self.running = True
            
            print(f"FaceNet Web Interface started on http://localhost:{self.port}")
            print("Press Ctrl+C to stop")
            
            # Start server
            self.server.serve_forever()
        except KeyboardInterrupt:
            self.stop()
        except Exception as e:
            print(f"Error starting web interface: {e}")
    
    def stop(self):
        """Stop the web interface server."""
        self.running = False
        if self.server:
            self.server.shutdown()
        print("Web interface stopped")

class WebInterfaceHandler(BaseHTTPRequestHandler):
    """HTTP request handler for web interface."""
    
    def do_GET(self):
        """Handle GET requests."""
        try:
            if self.path == '/':
                self.serve_main_page()
            elif self.path == '/api/status':
                self.serve_status()
            elif self.path == '/api/stats':
                self.serve_stats()
            elif self.path == '/api/health':
                self.serve_health()
            elif self.path == '/api/embeddings':
                self.serve_embeddings()
            elif self.path == '/api/users':
                self.serve_users()
            else:
                self.send_error(404)
        except Exception as e:
            self.send_error(500, str(e))
    
    def do_POST(self):
        """Handle POST requests."""
        try:
            if self.path == '/api/generate_embedding':
                self.handle_generate_embedding()
            elif self.path == '/api/recognize_face':
                self.handle_recognize_face()
            elif self.path == '/api/process_attendance':
                self.handle_process_attendance()
            elif self.path == '/api/save_embedding':
                self.handle_save_embedding()
            elif self.path == '/api/delete_embedding':
                self.handle_delete_embedding()
            elif self.path == '/api/backup':
                self.handle_backup()
            elif self.path == '/api/restore':
                self.handle_restore()
            elif self.path == '/api/maintenance':
                self.handle_maintenance()
            else:
                self.send_error(404)
        except Exception as e:
            self.send_error(500, str(e))
    
    def serve_main_page(self):
        """Serve the main web interface page."""
        html = self.get_main_page_html()
        
        self.send_response(200)
        self.send_header('Content-type', 'text/html')
        self.end_headers()
        self.wfile.write(html.encode())
    
    def serve_status(self):
        """Serve service status as JSON."""
        try:
            status = {
                'timestamp': datetime.now().isoformat(),
                'service': 'FaceNet',
                'version': '1.0',
                'status': 'running'
            }
            
            # Check database
            try:
                from facenet_database import db
                status['database'] = 'connected' if db.is_connected() else 'disconnected'
            except:
                status['database'] = 'error'
            
            # Check service
            try:
                from facenet_service import FaceNetService
                service = FaceNetService()
                status['facenet_service'] = 'running'
            except:
                status['facenet_service'] = 'error'
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(status, indent=2).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def serve_stats(self):
        """Serve statistics as JSON."""
        try:
            stats = {}
            
            # Get database stats
            try:
                from facenet_database import db
                if db.is_connected():
                    stats['database'] = db.get_embedding_stats()
                else:
                    stats['database'] = {'error': 'Database not connected'}
            except Exception as e:
                stats['database'] = {'error': str(e)}
            
            # Get system stats
            try:
                import psutil
                stats['system'] = {
                    'cpu_percent': psutil.cpu_percent(interval=1),
                    'memory_percent': psutil.virtual_memory().percent,
                    'disk_percent': psutil.disk_usage('/').percent
                }
            except ImportError:
                stats['system'] = {'error': 'psutil not available'}
            except Exception as e:
                stats['system'] = {'error': str(e)}
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(stats, indent=2).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def serve_health(self):
        """Serve health check as JSON."""
        try:
            from facenet_health_check import FaceNetHealthCheck
            
            health_check = FaceNetHealthCheck()
            all_passed, critical_failed = health_check.run_checks()
            
            health_data = {
                'status': 'healthy' if all_passed else 'unhealthy',
                'critical_failed': critical_failed,
                'timestamp': datetime.now().isoformat(),
                'results': health_check.results
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(health_data, indent=2).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def serve_embeddings(self):
        """Serve embeddings list as JSON."""
        try:
            from facenet_database import db
            
            if db.is_connected():
                embeddings = db.get_all_embeddings()
                embeddings_list = []
                
                for user_id, data in embeddings.items():
                    embeddings_list.append({
                        'user_id': user_id,
                        'nim': data['nim'],
                        'nama': data['nama'],
                        'embedding_dimensions': len(data['embedding'])
                    })
                
                self.send_response(200)
                self.send_header('Content-type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps(embeddings_list, indent=2).encode())
            else:
                self.send_error(500, 'Database not connected')
        except Exception as e:
            self.send_error(500, str(e))
    
    def serve_users(self):
        """Serve users list as JSON."""
        try:
            from facenet_database import db
            
            if db.is_connected():
                # This would typically query the users table
                # For now, return a placeholder
                users = [
                    {'id': 1, 'nim': '123456', 'nama': 'John Doe', 'email': 'john@example.com'},
                    {'id': 2, 'nim': '789012', 'nama': 'Jane Smith', 'email': 'jane@example.com'}
                ]
                
                self.send_response(200)
                self.send_header('Content-type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps(users, indent=2).encode())
            else:
                self.send_error(500, 'Database not connected')
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_generate_embedding(self):
        """Handle embedding generation request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            image_data = data.get('image')
            if not image_data:
                self.send_error(400, 'Image data required')
                return
            
            # Generate embedding
            from facenet_service import FaceNetService
            service = FaceNetService()
            
            start_time = time.time()
            embedding = service.generate_embedding(image_data)
            end_time = time.time()
            
            if embedding:
                response = {
                    'success': True,
                    'embedding': embedding,
                    'duration': end_time - start_time
                }
            else:
                response = {
                    'success': False,
                    'error': 'Failed to generate embedding'
                }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_recognize_face(self):
        """Handle face recognition request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            image_data = data.get('image')
            threshold = data.get('threshold', 1.0)
            
            if not image_data:
                self.send_error(400, 'Image data required')
                return
            
            # Recognize face
            from facenet_service import FaceNetService
            service = FaceNetService()
            
            start_time = time.time()
            result = service.recognize_face(image_data, threshold)
            end_time = time.time()
            
            response = {
                'success': True,
                'result': result,
                'duration': end_time - start_time
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_process_attendance(self):
        """Handle attendance processing request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            image_data = data.get('image')
            threshold = data.get('threshold', 1.0)
            
            if not image_data:
                self.send_error(400, 'Image data required')
                return
            
            # Process attendance
            from facenet_service import FaceNetService
            service = FaceNetService()
            
            start_time = time.time()
            result = service.process_attendance(image_data, threshold)
            end_time = time.time()
            
            response = {
                'success': True,
                'result': result,
                'duration': end_time - start_time
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_save_embedding(self):
        """Handle save embedding request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            user_id = data.get('user_id')
            embedding = data.get('embedding')
            
            if not user_id or not embedding:
                self.send_error(400, 'User ID and embedding required')
                return
            
            # Save embedding
            from facenet_service import FaceNetService
            service = FaceNetService()
            
            success = service.save_embedding_to_database(user_id, embedding)
            
            response = {
                'success': success,
                'message': 'Embedding saved successfully' if success else 'Failed to save embedding'
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_delete_embedding(self):
        """Handle delete embedding request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            user_id = data.get('user_id')
            
            if not user_id:
                self.send_error(400, 'User ID required')
                return
            
            # Delete embedding
            from facenet_service import FaceNetService
            service = FaceNetService()
            
            success = service.delete_embedding_from_database(user_id)
            
            response = {
                'success': success,
                'message': 'Embedding deleted successfully' if success else 'Failed to delete embedding'
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_backup(self):
        """Handle backup request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            backup_name = data.get('name')
            include_models = data.get('include_models', True)
            include_logs = data.get('include_logs', True)
            
            # Create backup
            from facenet_backup import FaceNetBackup
            
            backup_manager = FaceNetBackup()
            backup_path = backup_manager.create_backup(backup_name, include_models, include_logs)
            
            response = {
                'success': True,
                'backup_path': backup_path,
                'message': 'Backup created successfully'
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_restore(self):
        """Handle restore request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            backup_file = data.get('file')
            restore_models = data.get('restore_models', True)
            restore_logs = data.get('restore_logs', True)
            
            if not backup_file:
                self.send_error(400, 'Backup file required')
                return
            
            # Restore backup
            from facenet_backup import FaceNetBackup
            
            backup_manager = FaceNetBackup()
            success = backup_manager.restore_backup(backup_file, restore_models, restore_logs)
            
            response = {
                'success': success,
                'message': 'Backup restored successfully' if success else 'Failed to restore backup'
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def handle_maintenance(self):
        """Handle maintenance request."""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            maintenance_type = data.get('type', 'full')
            
            # Run maintenance
            from facenet_maintenance import FaceNetMaintenance
            
            maintenance = FaceNetMaintenance()
            
            if maintenance_type == 'full':
                results = maintenance.run_full_maintenance()
            elif maintenance_type == 'cleanup':
                maintenance.cleanup_temp_files()
                maintenance.cleanup_logs()
                maintenance.cleanup_old_embeddings()
                results = {'status': 'cleanup completed'}
            elif maintenance_type == 'optimize':
                maintenance.optimize_database()
                results = {'status': 'optimization completed'}
            else:
                results = {'status': 'unknown maintenance type'}
            
            response = {
                'success': True,
                'results': results,
                'message': 'Maintenance completed successfully'
            }
            
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(response).encode())
        except Exception as e:
            self.send_error(500, str(e))
    
    def get_main_page_html(self) -> str:
        """Get main page HTML."""
        return """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FaceNet Web Interface</title>
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
        .tabs {
            display: flex;
            margin-bottom: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            background: #f8f9fa;
            border: none;
            transition: background 0.3s;
        }
        .tab.active {
            background: #667eea;
            color: white;
        }
        .tab:hover {
            background: #e9ecef;
        }
        .tab.active:hover {
            background: #5a6fd8;
        }
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        .result.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .result.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 {
            margin-top: 0;
            color: #667eea;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .file-upload:hover {
            border-color: #667eea;
        }
        .file-upload.dragover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FaceNet Web Interface</h1>
            <p>Manage your FaceNet service through this web interface</p>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('overview')">Overview</button>
            <button class="tab" onclick="showTab('embeddings')">Embeddings</button>
            <button class="tab" onclick="showTab('recognition')">Recognition</button>
            <button class="tab" onclick="showTab('attendance')">Attendance</button>
            <button class="tab" onclick="showTab('backup')">Backup</button>
            <button class="tab" onclick="showTab('maintenance')">Maintenance</button>
        </div>
        
        <div id="overview" class="tab-content active">
            <h2>Service Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-value" id="total-users">-</div>
                </div>
                <div class="stat-card">
                    <h3>Users with Embeddings</h3>
                    <div class="stat-value" id="users-with-embeddings">-</div>
                </div>
                <div class="stat-card">
                    <h3>Coverage</h3>
                    <div class="stat-value" id="coverage">-</div>
                </div>
                <div class="stat-card">
                    <h3>Service Status</h3>
                    <div class="stat-value" id="service-status">-</div>
                </div>
            </div>
            <button class="btn" onclick="refreshStats()">Refresh Stats</button>
        </div>
        
        <div id="embeddings" class="tab-content">
            <h2>Face Embeddings</h2>
            <div class="form-group">
                <label for="embedding-image">Upload Image:</label>
                <div class="file-upload" onclick="document.getElementById('embedding-image').click()">
                    <p>Click to upload image or drag and drop</p>
                    <input type="file" id="embedding-image" accept="image/*" style="display: none;">
                </div>
            </div>
            <div class="form-group">
                <label for="embedding-user-id">User ID:</label>
                <input type="number" id="embedding-user-id" placeholder="Enter user ID">
            </div>
            <button class="btn" onclick="generateEmbedding()">Generate Embedding</button>
            <button class="btn btn-success" onclick="saveEmbedding()">Save Embedding</button>
            <div id="embedding-result" class="result hidden"></div>
        </div>
        
        <div id="recognition" class="tab-content">
            <h2>Face Recognition</h2>
            <div class="form-group">
                <label for="recognition-image">Upload Image:</label>
                <div class="file-upload" onclick="document.getElementById('recognition-image').click()">
                    <p>Click to upload image or drag and drop</p>
                    <input type="file" id="recognition-image" accept="image/*" style="display: none;">
                </div>
            </div>
            <div class="form-group">
                <label for="recognition-threshold">Threshold:</label>
                <input type="number" id="recognition-threshold" value="1.0" step="0.1" min="0" max="2">
            </div>
            <button class="btn" onclick="recognizeFace()">Recognize Face</button>
            <div id="recognition-result" class="result hidden"></div>
        </div>
        
        <div id="attendance" class="tab-content">
            <h2>Attendance Processing</h2>
            <div class="form-group">
                <label for="attendance-image">Upload Image:</label>
                <div class="file-upload" onclick="document.getElementById('attendance-image').click()">
                    <p>Click to upload image or drag and drop</p>
                    <input type="file" id="attendance-image" accept="image/*" style="display: none;">
                </div>
            </div>
            <div class="form-group">
                <label for="attendance-threshold">Threshold:</label>
                <input type="number" id="attendance-threshold" value="1.0" step="0.1" min="0" max="2">
            </div>
            <button class="btn" onclick="processAttendance()">Process Attendance</button>
            <div id="attendance-result" class="result hidden"></div>
        </div>
        
        <div id="backup" class="tab-content">
            <h2>Backup & Restore</h2>
            <div class="form-group">
                <label for="backup-name">Backup Name:</label>
                <input type="text" id="backup-name" placeholder="Enter backup name">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="include-models" checked> Include Models
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="include-logs" checked> Include Logs
                </label>
            </div>
            <button class="btn" onclick="createBackup()">Create Backup</button>
            <div id="backup-result" class="result hidden"></div>
            
            <hr>
            <h3>Restore Backup</h3>
            <div class="form-group">
                <label for="restore-file">Backup File:</label>
                <input type="file" id="restore-file" accept=".zip">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="restore-models" checked> Restore Models
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="restore-logs" checked> Restore Logs
                </label>
            </div>
            <button class="btn btn-danger" onclick="restoreBackup()">Restore Backup</button>
            <div id="restore-result" class="result hidden"></div>
        </div>
        
        <div id="maintenance" class="tab-content">
            <h2>Maintenance</h2>
            <button class="btn" onclick="runMaintenance('cleanup')">Cleanup</button>
            <button class="btn" onclick="runMaintenance('optimize')">Optimize</button>
            <button class="btn btn-success" onclick="runMaintenance('full')">Full Maintenance</button>
            <div id="maintenance-result" class="result hidden"></div>
        </div>
    </div>
    
    <script>
        let currentEmbedding = null;
        
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function refreshStats() {
            fetch('/api/stats')
                .then(response => response.json())
                .then(data => {
                    if (data.database && !data.database.error) {
                        document.getElementById('total-users').textContent = data.database.total_users || 0;
                        document.getElementById('users-with-embeddings').textContent = data.database.users_with_embeddings || 0;
                        document.getElementById('coverage').textContent = (data.database.coverage_percentage || 0).toFixed(1) + '%';
                    }
                    
                    if (data.system && !data.system.error) {
                        document.getElementById('service-status').textContent = 'Running';
                    } else {
                        document.getElementById('service-status').textContent = 'Error';
                    }
                })
                .catch(error => {
                    console.error('Error fetching stats:', error);
                });
        }
        
        function generateEmbedding() {
            const fileInput = document.getElementById('embedding-image');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select an image file');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const imageData = e.target.result;
                
                fetch('/api/generate_embedding', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ image: imageData })
                })
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('embedding-result');
                    resultDiv.classList.remove('hidden');
                    
                    if (data.success) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML = `
                            <h4>Embedding Generated Successfully</h4>
                            <p>Duration: ${data.duration.toFixed(3)}s</p>
                            <p>Dimensions: ${data.embedding.length}</p>
                        `;
                        currentEmbedding = data.embedding;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML = `<h4>Error</h4><p>${data.error}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            };
            reader.readAsDataURL(file);
        }
        
        function saveEmbedding() {
            const userId = document.getElementById('embedding-user-id').value;
            
            if (!userId) {
                alert('Please enter a user ID');
                return;
            }
            
            if (!currentEmbedding) {
                alert('Please generate an embedding first');
                return;
            }
            
            fetch('/api/save_embedding', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    user_id: parseInt(userId), 
                    embedding: currentEmbedding 
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('embedding-result');
                resultDiv.classList.remove('hidden');
                
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `<h4>Success</h4><p>${data.message}</p>`;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h4>Error</h4><p>${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        function recognizeFace() {
            const fileInput = document.getElementById('recognition-image');
            const file = fileInput.files[0];
            const threshold = parseFloat(document.getElementById('recognition-threshold').value);
            
            if (!file) {
                alert('Please select an image file');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const imageData = e.target.result;
                
                fetch('/api/recognize_face', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        image: imageData, 
                        threshold: threshold 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('recognition-result');
                    resultDiv.classList.remove('hidden');
                    
                    if (data.success) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML = `
                            <h4>Recognition Result</h4>
                            <p>Duration: ${data.duration.toFixed(3)}s</p>
                            <pre>${JSON.stringify(data.result, null, 2)}</pre>
                        `;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML = `<h4>Error</h4><p>${data.error}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            };
            reader.readAsDataURL(file);
        }
        
        function processAttendance() {
            const fileInput = document.getElementById('attendance-image');
            const file = fileInput.files[0];
            const threshold = parseFloat(document.getElementById('attendance-threshold').value);
            
            if (!file) {
                alert('Please select an image file');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const imageData = e.target.result;
                
                fetch('/api/process_attendance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        image: imageData, 
                        threshold: threshold 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('attendance-result');
                    resultDiv.classList.remove('hidden');
                    
                    if (data.success) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML = `
                            <h4>Attendance Processed</h4>
                            <p>Duration: ${data.duration.toFixed(3)}s</p>
                            <pre>${JSON.stringify(data.result, null, 2)}</pre>
                        `;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML = `<h4>Error</h4><p>${data.error}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            };
            reader.readAsDataURL(file);
        }
        
        function createBackup() {
            const backupName = document.getElementById('backup-name').value;
            const includeModels = document.getElementById('include-models').checked;
            const includeLogs = document.getElementById('include-logs').checked;
            
            fetch('/api/backup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    name: backupName, 
                    include_models: includeModels, 
                    include_logs: includeLogs 
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('backup-result');
                resultDiv.classList.remove('hidden');
                
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h4>Backup Created</h4>
                        <p>${data.message}</p>
                        <p>Path: ${data.backup_path}</p>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h4>Error</h4><p>${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        function restoreBackup() {
            const fileInput = document.getElementById('restore-file');
            const file = fileInput.files[0];
            const restoreModels = document.getElementById('restore-models').checked;
            const restoreLogs = document.getElementById('restore-logs').checked;
            
            if (!file) {
                alert('Please select a backup file');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const fileData = e.target.result;
                
                fetch('/api/restore', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        file: fileData, 
                        restore_models: restoreModels, 
                        restore_logs: restoreLogs 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById('restore-result');
                    resultDiv.classList.remove('hidden');
                    
                    if (data.success) {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML = `<h4>Backup Restored</h4><p>${data.message}</p>`;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML = `<h4>Error</h4><p>${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            };
            reader.readAsDataURL(file);
        }
        
        function runMaintenance(type) {
            fetch('/api/maintenance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type: type })
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('maintenance-result');
                resultDiv.classList.remove('hidden');
                
                if (data.success) {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h4>Maintenance Completed</h4>
                        <p>${data.message}</p>
                        <pre>${JSON.stringify(data.results, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `<h4>Error</h4><p>${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            refreshStats();
        });
    </script>
</body>
</html>
        """

def main():
    """Main function."""
    print("FaceNet Web Interface")
    print("=" * 50)
    
    # Check if port is available
    import socket
    port = 8081
    
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.bind(('localhost', port))
    except OSError:
        print(f"Port {port} is already in use. Trying port {port + 1}")
        port += 1
    
    # Start web interface
    web_interface = FaceNetWebInterface(port)
    web_interface.start()

if __name__ == '__main__':
    main()
