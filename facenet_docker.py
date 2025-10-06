#!/usr/bin/env python3
"""
FaceNet Docker Support

This script provides Docker support for FaceNet service.
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

class FaceNetDocker:
    """Docker manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Docker manager."""
        self.image_name = 'facenet'
        self.container_name = 'facenet-container'
        self.working_directory = os.getcwd()
        self.port = 8080
    
    def create_dockerfile(self) -> bool:
        """Create Dockerfile for FaceNet."""
        try:
            dockerfile_content = """# FaceNet Dockerfile
FROM python:3.8-slim

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \\
    build-essential \\
    cmake \\
    libopencv-dev \\
    libgtk-3-dev \\
    libavcodec-dev \\
    libavformat-dev \\
    libswscale-dev \\
    libv4l-dev \\
    libxvidcore-dev \\
    libx264-dev \\
    libjpeg-dev \\
    libpng-dev \\
    libtiff-dev \\
    libatlas-base-dev \\
    gfortran \\
    wget \\
    curl \\
    && rm -rf /var/lib/apt/lists/*

# Copy requirements first for better caching
COPY requirements.txt .

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Copy application files
COPY . .

# Create necessary directories
RUN mkdir -p logs debug_images backups

# Set environment variables
ENV PYTHONPATH=/app
ENV PYTHONUNBUFFERED=1

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \\
    CMD python -c "import requests; requests.get('http://localhost:8080/health')" || exit 1

# Start command
CMD ["python", "facenet_service.py"]
"""
            
            with open('Dockerfile', 'w') as f:
                f.write(dockerfile_content)
            
            print("✓ Dockerfile created")
            return True
        except Exception as e:
            print(f"✗ Error creating Dockerfile: {e}")
            return False
    
    def create_docker_compose(self) -> bool:
        """Create docker-compose.yml for FaceNet."""
        try:
            compose_content = f"""version: '3.8'

services:
  facenet:
    build: .
    container_name: {self.container_name}
    ports:
      - "{self.port}:8080"
    volumes:
      - ./logs:/app/logs
      - ./debug_images:/app/debug_images
      - ./backups:/app/backups
      - ./facenet-master:/app/facenet-master
    environment:
      - PYTHONPATH=/app
      - PYTHONUNBUFFERED=1
    restart: unless-stopped
    depends_on:
      - mysql
    networks:
      - facenet-network

  mysql:
    image: mysql:8.0
    container_name: facenet-mysql
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: absen_db
      MYSQL_USER: facenet
      MYSQL_PASSWORD: facenetpassword
    volumes:
      - mysql_data:/var/lib/mysql
      - ./absen_db.sql:/docker-entrypoint-initdb.d/absen_db.sql
    ports:
      - "3306:3306"
    restart: unless-stopped
    networks:
      - facenet-network

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: facenet-phpmyadmin
    environment:
      PMA_HOST: mysql
      PMA_USER: root
      PMA_PASSWORD: rootpassword
    ports:
      - "8081:80"
    depends_on:
      - mysql
    networks:
      - facenet-network

volumes:
  mysql_data:

networks:
  facenet-network:
    driver: bridge
"""
            
            with open('docker-compose.yml', 'w') as f:
                f.write(compose_content)
            
            print("✓ docker-compose.yml created")
            return True
        except Exception as e:
            print(f"✗ Error creating docker-compose.yml: {e}")
            return False
    
    def create_dockerignore(self) -> bool:
        """Create .dockerignore file."""
        try:
            dockerignore_content = """# Python
__pycache__/
*.py[cod]
*$py.class
*.so
.Python
build/
develop-eggs/
dist/
downloads/
eggs/
.eggs/
lib/
lib64/
parts/
sdist/
var/
wheels/
*.egg-info/
.installed.cfg
*.egg

# Virtual environments
venv/
env/
ENV/

# IDE
.vscode/
.idea/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Logs
*.log
logs/

# Debug images
debug_images/

# Backups
backups/

# Git
.git/
.gitignore

# Docker
Dockerfile
docker-compose.yml
.dockerignore

# Documentation
*.md
README*

# Test files
test_*
*_test.py
"""
            
            with open('.dockerignore', 'w') as f:
                f.write(dockerignore_content)
            
            print("✓ .dockerignore created")
            return True
        except Exception as e:
            print(f"✗ Error creating .dockerignore: {e}")
            return False
    
    def build_image(self) -> bool:
        """Build Docker image."""
        try:
            print("Building Docker image...")
            result = subprocess.run(['docker', 'build', '-t', self.image_name, '.'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error building image: {result.stderr}")
                return False
            
            print(f"✓ Docker image {self.image_name} built successfully")
            return True
        except Exception as e:
            print(f"✗ Error building image: {e}")
            return False
    
    def run_container(self) -> bool:
        """Run Docker container."""
        try:
            print("Running Docker container...")
            result = subprocess.run([
                'docker', 'run', '-d',
                '--name', self.container_name,
                '-p', f'{self.port}:8080',
                '-v', f'{self.working_directory}/logs:/app/logs',
                '-v', f'{self.working_directory}/debug_images:/app/debug_images',
                '-v', f'{self.working_directory}/backups:/app/backups',
                '-v', f'{self.working_directory}/facenet-master:/app/facenet-master',
                '-e', 'PYTHONPATH=/app',
                '-e', 'PYTHONUNBUFFERED=1',
                '--restart', 'unless-stopped',
                self.image_name
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error running container: {result.stderr}")
                return False
            
            print(f"✓ Docker container {self.container_name} started")
            return True
        except Exception as e:
            print(f"✗ Error running container: {e}")
            return False
    
    def stop_container(self) -> bool:
        """Stop Docker container."""
        try:
            print("Stopping Docker container...")
            result = subprocess.run(['docker', 'stop', self.container_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error stopping container: {result.stderr}")
                return False
            
            print(f"✓ Docker container {self.container_name} stopped")
            return True
        except Exception as e:
            print(f"✗ Error stopping container: {e}")
            return False
    
    def remove_container(self) -> bool:
        """Remove Docker container."""
        try:
            print("Removing Docker container...")
            result = subprocess.run(['docker', 'rm', self.container_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error removing container: {result.stderr}")
                return False
            
            print(f"✓ Docker container {self.container_name} removed")
            return True
        except Exception as e:
            print(f"✗ Error removing container: {e}")
            return False
    
    def remove_image(self) -> bool:
        """Remove Docker image."""
        try:
            print("Removing Docker image...")
            result = subprocess.run(['docker', 'rmi', self.image_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error removing image: {result.stderr}")
                return False
            
            print(f"✓ Docker image {self.image_name} removed")
            return True
        except Exception as e:
            print(f"✗ Error removing image: {e}")
            return False
    
    def get_container_status(self) -> Dict:
        """Get container status."""
        try:
            result = subprocess.run(['docker', 'ps', '-a', '--filter', f'name={self.container_name}', '--format', 'json'], 
                                  capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            if not result.stdout.strip():
                return {'status': 'not_found'}
            
            container_info = json.loads(result.stdout)
            return {
                'status': container_info.get('State', 'unknown'),
                'image': container_info.get('Image', 'unknown'),
                'ports': container_info.get('Ports', ''),
                'created': container_info.get('CreatedAt', 'unknown')
            }
        except Exception as e:
            return {'error': str(e)}
    
    def get_container_logs(self, lines: int = 50) -> str:
        """Get container logs."""
        try:
            result = subprocess.run(['docker', 'logs', '--tail', str(lines), self.container_name], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                return f"Error getting logs: {result.stderr}"
            
            return result.stdout
        except Exception as e:
            return f"Error getting logs: {e}"
    
    def exec_command(self, command: str) -> str:
        """Execute command in container."""
        try:
            result = subprocess.run(['docker', 'exec', self.container_name, 'bash', '-c', command], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                return f"Error executing command: {result.stderr}"
            
            return result.stdout
        except Exception as e:
            return f"Error executing command: {e}"
    
    def copy_file_to_container(self, local_path: str, container_path: str) -> bool:
        """Copy file to container."""
        try:
            result = subprocess.run(['docker', 'cp', local_path, f'{self.container_name}:{container_path}'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error copying file: {result.stderr}")
                return False
            
            print(f"✓ File copied to container: {local_path} -> {container_path}")
            return True
        except Exception as e:
            print(f"✗ Error copying file: {e}")
            return False
    
    def copy_file_from_container(self, container_path: str, local_path: str) -> bool:
        """Copy file from container."""
        try:
            result = subprocess.run(['docker', 'cp', f'{self.container_name}:{container_path}', local_path], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error copying file: {result.stderr}")
                return False
            
            print(f"✓ File copied from container: {container_path} -> {local_path}")
            return True
        except Exception as e:
            print(f"✗ Error copying file: {e}")
            return False
    
    def setup_docker_environment(self) -> bool:
        """Setup Docker environment."""
        try:
            print("Setting up Docker environment...")
            
            # Create Docker files
            if not self.create_dockerfile():
                return False
            
            if not self.create_docker_compose():
                return False
            
            if not self.create_dockerignore():
                return False
            
            print("✓ Docker environment setup completed")
            return True
        except Exception as e:
            print(f"✗ Error setting up Docker environment: {e}")
            return False
    
    def deploy_with_docker_compose(self) -> bool:
        """Deploy with docker-compose."""
        try:
            print("Deploying with docker-compose...")
            result = subprocess.run(['docker-compose', 'up', '-d'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error deploying with docker-compose: {result.stderr}")
                return False
            
            print("✓ Deployed with docker-compose")
            return True
        except Exception as e:
            print(f"✗ Error deploying with docker-compose: {e}")
            return False
    
    def stop_docker_compose(self) -> bool:
        """Stop docker-compose services."""
        try:
            print("Stopping docker-compose services...")
            result = subprocess.run(['docker-compose', 'down'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error stopping docker-compose: {result.stderr}")
                return False
            
            print("✓ Docker-compose services stopped")
            return True
        except Exception as e:
            print(f"✗ Error stopping docker-compose: {e}")
            return False
    
    def get_docker_info(self) -> Dict:
        """Get comprehensive Docker information."""
        try:
            info = {
                'image_name': self.image_name,
                'container_name': self.container_name,
                'working_directory': self.working_directory,
                'port': self.port,
                'container_status': self.get_container_status(),
                'logs': self.get_container_logs(20)
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Docker Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_docker.py <command>")
        print("Commands:")
        print("  setup       - Setup Docker environment")
        print("  build       - Build Docker image")
        print("  run         - Run Docker container")
        print("  stop        - Stop Docker container")
        print("  remove      - Remove Docker container")
        print("  rmi         - Remove Docker image")
        print("  status      - Show container status")
        print("  logs        - Show container logs")
        print("  exec        - Execute command in container")
        print("  deploy      - Deploy with docker-compose")
        print("  stop-compose - Stop docker-compose services")
        print("  info        - Show Docker information")
        return
    
    command = sys.argv[1]
    docker_manager = FaceNetDocker()
    
    if command == 'setup':
        docker_manager.setup_docker_environment()
    elif command == 'build':
        docker_manager.build_image()
    elif command == 'run':
        docker_manager.run_container()
    elif command == 'stop':
        docker_manager.stop_container()
    elif command == 'remove':
        docker_manager.remove_container()
    elif command == 'rmi':
        docker_manager.remove_image()
    elif command == 'status':
        status = docker_manager.get_container_status()
        print(f"Container Status: {status}")
    elif command == 'logs':
        logs = docker_manager.get_container_logs()
        print(logs)
    elif command == 'exec':
        if len(sys.argv) < 3:
            print("Usage: python facenet_docker.py exec <command>")
            return
        cmd = ' '.join(sys.argv[2:])
        output = docker_manager.exec_command(cmd)
        print(output)
    elif command == 'deploy':
        docker_manager.deploy_with_docker_compose()
    elif command == 'stop-compose':
        docker_manager.stop_docker_compose()
    elif command == 'info':
        info = docker_manager.get_docker_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
