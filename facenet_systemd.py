#!/usr/bin/env python3
"""
FaceNet Systemd Service

This script provides systemd service management for FaceNet.
"""

import os
import sys
import json
import time
import subprocess
from datetime import datetime
from typing import Dict, List, Optional

# Add the facenet-master directory to Python path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'facenet-master'))

class FaceNetSystemdService:
    """Systemd service manager for FaceNet."""
    
    def __init__(self):
        """Initialize systemd service manager."""
        self.service_name = 'facenet'
        self.service_file = f'/etc/systemd/system/{self.service_name}.service'
        self.working_directory = os.getcwd()
        self.python_path = sys.executable
    
    def create_service_file(self) -> bool:
        """Create systemd service file."""
        try:
            service_content = f"""[Unit]
Description=FaceNet Service
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory={self.working_directory}
ExecStart={self.python_path} {self.working_directory}/facenet_service.py
ExecReload=/bin/kill -HUP $MAINPID
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=facenet

# Environment variables
Environment=PYTHONPATH={self.working_directory}
Environment=PYTHONUNBUFFERED=1

# Security settings
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths={self.working_directory}

[Install]
WantedBy=multi-user.target
"""
            
            with open(self.service_file, 'w') as f:
                f.write(service_content)
            
            print(f"✓ Service file created: {self.service_file}")
            return True
        except Exception as e:
            print(f"✗ Error creating service file: {e}")
            return False
    
    def install_service(self) -> bool:
        """Install and enable the service."""
        try:
            # Create service file
            if not self.create_service_file():
                return False
            
            # Reload systemd
            result = subprocess.run(['systemctl', 'daemon-reload'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error reloading systemd: {result.stderr}")
                return False
            
            # Enable service
            result = subprocess.run(['systemctl', 'enable', self.service_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error enabling service: {result.stderr}")
                return False
            
            print(f"✓ Service {self.service_name} installed and enabled")
            return True
        except Exception as e:
            print(f"✗ Error installing service: {e}")
            return False
    
    def uninstall_service(self) -> bool:
        """Uninstall the service."""
        try:
            # Stop service
            self.stop_service()
            
            # Disable service
            result = subprocess.run(['systemctl', 'disable', self.service_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error disabling service: {result.stderr}")
                return False
            
            # Remove service file
            if os.path.exists(self.service_file):
                os.remove(self.service_file)
                print(f"✓ Service file removed: {self.service_file}")
            
            # Reload systemd
            result = subprocess.run(['systemctl', 'daemon-reload'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error reloading systemd: {result.stderr}")
                return False
            
            print(f"✓ Service {self.service_name} uninstalled")
            return True
        except Exception as e:
            print(f"✗ Error uninstalling service: {e}")
            return False
    
    def start_service(self) -> bool:
        """Start the service."""
        try:
            result = subprocess.run(['systemctl', 'start', self.service_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error starting service: {result.stderr}")
                return False
            
            print(f"✓ Service {self.service_name} started")
            return True
        except Exception as e:
            print(f"✗ Error starting service: {e}")
            return False
    
    def stop_service(self) -> bool:
        """Stop the service."""
        try:
            result = subprocess.run(['systemctl', 'stop', self.service_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error stopping service: {result.stderr}")
                return False
            
            print(f"✓ Service {self.service_name} stopped")
            return True
        except Exception as e:
            print(f"✗ Error stopping service: {e}")
            return False
    
    def restart_service(self) -> bool:
        """Restart the service."""
        try:
            result = subprocess.run(['systemctl', 'restart', self.service_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error restarting service: {result.stderr}")
                return False
            
            print(f"✓ Service {self.service_name} restarted")
            return True
        except Exception as e:
            print(f"✗ Error restarting service: {e}")
            return False
    
    def reload_service(self) -> bool:
        """Reload the service."""
        try:
            result = subprocess.run(['systemctl', 'reload', self.service_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error reloading service: {result.stderr}")
                return False
            
            print(f"✓ Service {self.service_name} reloaded")
            return True
        except Exception as e:
            print(f"✗ Error reloading service: {e}")
            return False
    
    def get_service_status(self) -> Dict:
        """Get service status."""
        try:
            result = subprocess.run(['systemctl', 'status', self.service_name], 
                                  capture_output=True, text=True)
            
            status = {
                'active': False,
                'enabled': False,
                'running': False,
                'status': 'unknown',
                'output': result.stdout,
                'error': result.stderr
            }
            
            if result.returncode == 0:
                status['active'] = True
                status['running'] = True
                status['status'] = 'running'
            elif result.returncode == 3:
                status['active'] = False
                status['running'] = False
                status['status'] = 'stopped'
            else:
                status['status'] = 'error'
            
            # Check if enabled
            result = subprocess.run(['systemctl', 'is-enabled', self.service_name], 
                                  capture_output=True, text=True)
            if result.returncode == 0:
                status['enabled'] = True
            
            return status
        except Exception as e:
            return {
                'active': False,
                'enabled': False,
                'running': False,
                'status': 'error',
                'error': str(e)
            }
    
    def get_service_logs(self, lines: int = 50) -> str:
        """Get service logs."""
        try:
            result = subprocess.run(['journalctl', '-u', self.service_name, '-n', str(lines)], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                return f"Error getting logs: {result.stderr}"
            
            return result.stdout
        except Exception as e:
            return f"Error getting logs: {e}"
    
    def get_service_info(self) -> Dict:
        """Get comprehensive service information."""
        try:
            info = {
                'service_name': self.service_name,
                'service_file': self.service_file,
                'working_directory': self.working_directory,
                'python_path': self.python_path,
                'status': self.get_service_status(),
                'logs': self.get_service_logs(20)
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }
    
    def create_startup_script(self) -> bool:
        """Create startup script."""
        try:
            script_content = f"""#!/bin/bash
# FaceNet Startup Script

# Set working directory
cd {self.working_directory}

# Set environment variables
export PYTHONPATH={self.working_directory}
export PYTHONUNBUFFERED=1

# Start FaceNet service
{self.python_path} facenet_service.py
"""
            
            script_file = f'{self.working_directory}/start_facenet.sh'
            with open(script_file, 'w') as f:
                f.write(script_content)
            
            # Make executable
            os.chmod(script_file, 0o755)
            
            print(f"✓ Startup script created: {script_file}")
            return True
        except Exception as e:
            print(f"✗ Error creating startup script: {e}")
            return False
    
    def create_stop_script(self) -> bool:
        """Create stop script."""
        try:
            script_content = f"""#!/bin/bash
# FaceNet Stop Script

# Stop FaceNet service
systemctl stop {self.service_name}
"""
            
            script_file = f'{self.working_directory}/stop_facenet.sh'
            with open(script_file, 'w') as f:
                f.write(script_content)
            
            # Make executable
            os.chmod(script_file, 0o755)
            
            print(f"✓ Stop script created: {script_file}")
            return True
        except Exception as e:
            print(f"✗ Error creating stop script: {e}")
            return False
    
    def create_restart_script(self) -> bool:
        """Create restart script."""
        try:
            script_content = f"""#!/bin/bash
# FaceNet Restart Script

# Restart FaceNet service
systemctl restart {self.service_name}
"""
            
            script_file = f'{self.working_directory}/restart_facenet.sh'
            with open(script_file, 'w') as f:
                f.write(script_content)
            
            # Make executable
            os.chmod(script_file, 0o755)
            
            print(f"✓ Restart script created: {script_file}")
            return True
        except Exception as e:
            print(f"✗ Error creating restart script: {e}")
            return False
    
    def create_log_script(self) -> bool:
        """Create log viewing script."""
        try:
            script_content = f"""#!/bin/bash
# FaceNet Log Script

# Show FaceNet service logs
journalctl -u {self.service_name} -f
"""
            
            script_file = f'{self.working_directory}/logs_facenet.sh'
            with open(script_file, 'w') as f:
                f.write(script_content)
            
            # Make executable
            os.chmod(script_file, 0o755)
            
            print(f"✓ Log script created: {script_file}")
            return True
        except Exception as e:
            print(f"✗ Error creating log script: {e}")
            return False
    
    def create_all_scripts(self) -> bool:
        """Create all management scripts."""
        try:
            scripts_created = 0
            
            if self.create_startup_script():
                scripts_created += 1
            
            if self.create_stop_script():
                scripts_created += 1
            
            if self.create_restart_script():
                scripts_created += 1
            
            if self.create_log_script():
                scripts_created += 1
            
            print(f"✓ Created {scripts_created} management scripts")
            return scripts_created == 4
        except Exception as e:
            print(f"✗ Error creating scripts: {e}")
            return False
    
    def setup_auto_start(self) -> bool:
        """Setup auto-start on boot."""
        try:
            # Install service
            if not self.install_service():
                return False
            
            # Create management scripts
            if not self.create_all_scripts():
                return False
            
            print("✓ Auto-start setup completed")
            print("  Service will start automatically on boot")
            print("  Management scripts created in working directory")
            return True
        except Exception as e:
            print(f"✗ Error setting up auto-start: {e}")
            return False

def main():
    """Main function."""
    print("FaceNet Systemd Service Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_systemd.py <command>")
        print("Commands:")
        print("  install     - Install and enable service")
        print("  uninstall   - Uninstall service")
        print("  start       - Start service")
        print("  stop        - Stop service")
        print("  restart     - Restart service")
        print("  reload      - Reload service")
        print("  status      - Show service status")
        print("  logs        - Show service logs")
        print("  info        - Show service information")
        print("  setup       - Setup auto-start")
        return
    
    command = sys.argv[1]
    service_manager = FaceNetSystemdService()
    
    if command == 'install':
        service_manager.install_service()
    elif command == 'uninstall':
        service_manager.uninstall_service()
    elif command == 'start':
        service_manager.start_service()
    elif command == 'stop':
        service_manager.stop_service()
    elif command == 'restart':
        service_manager.restart_service()
    elif command == 'reload':
        service_manager.reload_service()
    elif command == 'status':
        status = service_manager.get_service_status()
        print(f"Service Status: {status['status']}")
        print(f"Active: {status['active']}")
        print(f"Enabled: {status['enabled']}")
        print(f"Running: {status['running']}")
    elif command == 'logs':
        logs = service_manager.get_service_logs()
        print(logs)
    elif command == 'info':
        info = service_manager.get_service_info()
        print(json.dumps(info, indent=2))
    elif command == 'setup':
        service_manager.setup_auto_start()
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
