#!/usr/bin/env python3
"""
FaceNet Google Cloud Support

This script provides Google Cloud support for FaceNet service.
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

class FaceNetGoogleCloud:
    """Google Cloud manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Google Cloud manager."""
        self.project_id = 'facenet-project'
        self.region = 'us-central1'
        self.zone = 'us-central1-a'
        self.instance_name = 'facenet-instance'
        self.machine_type = 'e2-medium'
        self.bucket_name = 'facenet-models'
        self.sql_instance = 'facenet-sql'
        self.sql_database = 'facenet_db'
    
    def create_project(self) -> bool:
        """Create Google Cloud project."""
        try:
            print("Creating Google Cloud project...")
            
            result = subprocess.run([
                'gcloud', 'projects', 'create', self.project_id
            ], capture_output=True, text=True)
            
            if result.returncode != 0 and 'already exists' not in result.stderr:
                print(f"✗ Error creating project: {result.stderr}")
                return False
            
            # Set project
            result = subprocess.run([
                'gcloud', 'config', 'set', 'project', self.project_id
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error setting project: {result.stderr}")
                return False
            
            print(f"✓ Project {self.project_id} created")
            return True
        except Exception as e:
            print(f"✗ Error creating project: {e}")
            return False
    
    def enable_apis(self) -> bool:
        """Enable required Google Cloud APIs."""
        try:
            print("Enabling Google Cloud APIs...")
            
            apis = [
                'compute.googleapis.com',
                'storage.googleapis.com',
                'sqladmin.googleapis.com',
                'container.googleapis.com',
                'run.googleapis.com'
            ]
            
            for api in apis:
                result = subprocess.run([
                    'gcloud', 'services', 'enable', api
                ], capture_output=True, text=True)
                
                if result.returncode != 0:
                    print(f"⚠ Warning: Error enabling {api}: {result.stderr}")
            
            print("✓ APIs enabled")
            return True
        except Exception as e:
            print(f"✗ Error enabling APIs: {e}")
            return False
    
    def create_compute_instance(self) -> bool:
        """Create Google Cloud Compute Engine instance."""
        try:
            print("Creating Compute Engine instance...")
            
            result = subprocess.run([
                'gcloud', 'compute', 'instances', 'create', self.instance_name,
                '--zone', self.zone,
                '--machine-type', self.machine_type,
                '--image-family', 'ubuntu-2004-lts',
                '--image-project', 'ubuntu-os-cloud',
                '--boot-disk-size', '20GB',
                '--boot-disk-type', 'pd-standard',
                '--tags', 'facenet-server',
                '--metadata', 'startup-script=' + self.get_startup_script()
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating instance: {result.stderr}")
                return False
            
            # Create firewall rule
            result = subprocess.run([
                'gcloud', 'compute', 'firewall-rules', 'create', 'facenet-firewall',
                '--allow', 'tcp:22,tcp:80,tcp:443,tcp:8080,tcp:3306',
                '--source-ranges', '0.0.0.0/0',
                '--target-tags', 'facenet-server'
            ], capture_output=True, text=True)
            
            if result.returncode != 0 and 'already exists' not in result.stderr:
                print(f"⚠ Warning: Error creating firewall rule: {result.stderr}")
            
            print(f"✓ Compute Engine instance {self.instance_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating instance: {e}")
            return False
    
    def create_cloud_storage_bucket(self) -> bool:
        """Create Google Cloud Storage bucket."""
        try:
            print("Creating Cloud Storage bucket...")
            
            result = subprocess.run([
                'gsutil', 'mb', f'gs://{self.bucket_name}'
            ], capture_output=True, text=True)
            
            if result.returncode != 0 and 'already exists' not in result.stderr:
                print(f"✗ Error creating bucket: {result.stderr}")
                return False
            
            print(f"✓ Cloud Storage bucket {self.bucket_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating bucket: {e}")
            return False
    
    def create_cloud_sql_instance(self) -> bool:
        """Create Google Cloud SQL instance."""
        try:
            print("Creating Cloud SQL instance...")
            
            result = subprocess.run([
                'gcloud', 'sql', 'instances', 'create', self.sql_instance,
                '--database-version', 'MYSQL_8_0',
                '--tier', 'db-f1-micro',
                '--region', self.region,
                '--root-password', 'rootpassword123'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating SQL instance: {result.stderr}")
                return False
            
            # Create database
            result = subprocess.run([
                'gcloud', 'sql', 'databases', 'create', self.sql_database,
                '--instance', self.sql_instance
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating database: {result.stderr}")
                return False
            
            print(f"✓ Cloud SQL instance {self.sql_instance} created")
            return True
        except Exception as e:
            print(f"✗ Error creating SQL instance: {e}")
            return False
    
    def create_kubernetes_cluster(self) -> bool:
        """Create Google Kubernetes Engine cluster."""
        try:
            print("Creating GKE cluster...")
            
            result = subprocess.run([
                'gcloud', 'container', 'clusters', 'create', 'facenet-cluster',
                '--zone', self.zone,
                '--num-nodes', '2',
                '--machine-type', 'e2-medium',
                '--enable-autoscaling',
                '--min-nodes', '1',
                '--max-nodes', '5'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating GKE cluster: {result.stderr}")
                return False
            
            print("✓ GKE cluster created")
            return True
        except Exception as e:
            print(f"✗ Error creating GKE cluster: {e}")
            return False
    
    def create_cloud_run_service(self) -> bool:
        """Create Google Cloud Run service."""
        try:
            print("Creating Cloud Run service...")
            
            # Build and push container image
            result = subprocess.run([
                'gcloud', 'builds', 'submit', '--tag', f'gcr.io/{self.project_id}/facenet'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error building container: {result.stderr}")
                return False
            
            # Deploy to Cloud Run
            result = subprocess.run([
                'gcloud', 'run', 'deploy', 'facenet-service',
                '--image', f'gcr.io/{self.project_id}/facenet',
                '--platform', 'managed',
                '--region', self.region,
                '--allow-unauthenticated',
                '--memory', '2Gi',
                '--cpu', '2'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error deploying to Cloud Run: {result.stderr}")
                return False
            
            print("✓ Cloud Run service created")
            return True
        except Exception as e:
            print(f"✗ Error creating Cloud Run service: {e}")
            return False
    
    def upload_models_to_storage(self) -> bool:
        """Upload FaceNet models to Cloud Storage."""
        try:
            print("Uploading models to Cloud Storage...")
            
            models_dir = 'facenet-master/models'
            if not os.path.exists(models_dir):
                print(f"⚠ Models directory {models_dir} not found")
                return False
            
            result = subprocess.run([
                'gsutil', '-m', 'cp', '-r', models_dir, f'gs://{self.bucket_name}/'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error uploading models: {result.stderr}")
                return False
            
            print("✓ Models uploaded to Cloud Storage")
            return True
        except Exception as e:
            print(f"✗ Error uploading models: {e}")
            return False
    
    def create_deployment_manager_template(self) -> bool:
        """Create Google Cloud Deployment Manager template."""
        try:
            print("Creating Deployment Manager template...")
            
            template = self.get_deployment_manager_template()
            
            with open('facenet-template.yaml', 'w') as f:
                f.write(template)
            
            print("✓ Deployment Manager template created")
            return True
        except Exception as e:
            print(f"✗ Error creating template: {e}")
            return False
    
    def get_deployment_manager_template(self) -> str:
        """Get Deployment Manager template."""
        return f"""imports:
- path: compute_instance.py
- path: storage_bucket.py
- path: sql_instance.py

resources:
- name: facenet-instance
  type: compute_instance.py
  properties:
    zone: {self.zone}
    machineType: {self.machine_type}
    imageFamily: ubuntu-2004-lts
    imageProject: ubuntu-os-cloud
    bootDiskSize: 20
    bootDiskType: pd-standard
    tags: ['facenet-server']
    metadata:
      startup-script: |
        #!/bin/bash
        apt-get update
        apt-get install -y python3 python3-pip
        pip3 install tensorflow numpy opencv-python pillow
        systemctl enable apache2
        systemctl start apache2

- name: facenet-bucket
  type: storage_bucket.py
  properties:
    name: {self.bucket_name}
    location: {self.region}

- name: facenet-sql
  type: sql_instance.py
  properties:
    name: {self.sql_instance}
    databaseVersion: MYSQL_8_0
    tier: db-f1-micro
    region: {self.region}
    rootPassword: rootpassword123
"""
    
    def deploy_with_deployment_manager(self) -> bool:
        """Deploy with Deployment Manager."""
        try:
            print("Deploying with Deployment Manager...")
            
            result = subprocess.run([
                'gcloud', 'deployment-manager', 'deployments', 'create', 'facenet-deployment',
                '--config', 'facenet-template.yaml'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error deploying with Deployment Manager: {result.stderr}")
                return False
            
            print("✓ Deployment Manager deployment created")
            return True
        except Exception as e:
            print(f"✗ Error deploying with Deployment Manager: {e}")
            return False
    
    def get_startup_script(self) -> str:
        """Get startup script for Compute Engine."""
        return """#!/bin/bash
# FaceNet GCE Startup Script

# Update system
apt-get update -y

# Install Python 3.8
apt-get install -y python3 python3-pip

# Install system dependencies
apt-get install -y gcc g++ make cmake
apt-get install -y libopencv-dev python3-opencv
apt-get install -y libmysqlclient-dev

# Install Python dependencies
pip3 install tensorflow==1.7
pip3 install numpy scipy scikit-learn
pip3 install opencv-python pillow
pip3 install h5py matplotlib requests psutil
pip3 install mysql-connector-python

# Install Apache and PHP
apt-get install -y apache2 php php-mysql php-curl

# Start Apache
systemctl start apache2
systemctl enable apache2

# Install MySQL
apt-get install -y mysql-server
systemctl start mysql
systemctl enable mysql

# Create application directory
mkdir -p /var/www/html/facenet
cd /var/www/html/facenet

# Download FaceNet models from Cloud Storage
gsutil -m cp -r gs://facenet-models/* .

# Set permissions
chown -R www-data:www-data /var/www/html/facenet
chmod -R 755 /var/www/html/facenet

# Create systemd service
cat > /etc/systemd/system/facenet.service << EOF
[Unit]
Description=FaceNet Service
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/facenet
ExecStart=/usr/bin/python3 facenet_service.py
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Enable and start service
systemctl daemon-reload
systemctl enable facenet
systemctl start facenet

echo "FaceNet setup completed" >> /var/log/facenet-setup.log
"""
    
    def get_instance_status(self) -> Dict:
        """Get Compute Engine instance status."""
        try:
            result = subprocess.run([
                'gcloud', 'compute', 'instances', 'describe', self.instance_name,
                '--zone', self.zone,
                '--format', 'json'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            instance_info = json.loads(result.stdout)
            return {
                'name': instance_info['name'],
                'status': instance_info['status'],
                'machineType': instance_info['machineType'],
                'zone': instance_info['zone'],
                'creationTimestamp': instance_info['creationTimestamp']
            }
        except Exception as e:
            return {'error': str(e)}
    
    def get_storage_status(self) -> Dict:
        """Get Cloud Storage bucket status."""
        try:
            result = subprocess.run([
                'gsutil', 'ls', '-L', '-b', f'gs://{self.bucket_name}'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return {'status': result.stdout}
        except Exception as e:
            return {'error': str(e)}
    
    def get_sql_status(self) -> Dict:
        """Get Cloud SQL instance status."""
        try:
            result = subprocess.run([
                'gcloud', 'sql', 'instances', 'describe', self.sql_instance,
                '--format', 'json'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            sql_info = json.loads(result.stdout)
            return {
                'name': sql_info['name'],
                'state': sql_info['state'],
                'databaseVersion': sql_info['databaseVersion'],
                'region': sql_info['region'],
                'settings': sql_info['settings']
            }
        except Exception as e:
            return {'error': str(e)}
    
    def deploy_to_google_cloud(self) -> bool:
        """Deploy FaceNet to Google Cloud."""
        try:
            print("Deploying FaceNet to Google Cloud...")
            
            # Create project
            if not self.create_project():
                return False
            
            # Enable APIs
            if not self.enable_apis():
                return False
            
            # Create storage bucket
            if not self.create_cloud_storage_bucket():
                return False
            
            # Upload models
            if not self.upload_models_to_storage():
                return False
            
            # Create Compute Engine instance
            if not self.create_compute_instance():
                return False
            
            # Create Cloud SQL instance
            if not self.create_cloud_sql_instance():
                return False
            
            print("✓ FaceNet deployed to Google Cloud successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying to Google Cloud: {e}")
            return False
    
    def get_google_cloud_info(self) -> Dict:
        """Get comprehensive Google Cloud information."""
        try:
            info = {
                'project_id': self.project_id,
                'region': self.region,
                'zone': self.zone,
                'instance_name': self.instance_name,
                'machine_type': self.machine_type,
                'bucket_name': self.bucket_name,
                'sql_instance': self.sql_instance,
                'sql_database': self.sql_database,
                'instance_status': self.get_instance_status(),
                'storage_status': self.get_storage_status(),
                'sql_status': self.get_sql_status()
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Google Cloud Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_google_cloud.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet to Google Cloud")
        print("  instance    - Create Compute Engine instance")
        print("  storage     - Create Cloud Storage bucket")
        print("  sql         - Create Cloud SQL instance")
        print("  gke         - Create GKE cluster")
        print("  cloudrun    - Create Cloud Run service")
        print("  status      - Show Google Cloud resources status")
        print("  info        - Show Google Cloud information")
        return
    
    command = sys.argv[1]
    gcp_manager = FaceNetGoogleCloud()
    
    if command == 'deploy':
        gcp_manager.deploy_to_google_cloud()
    elif command == 'instance':
        gcp_manager.create_compute_instance()
    elif command == 'storage':
        gcp_manager.create_cloud_storage_bucket()
    elif command == 'sql':
        gcp_manager.create_cloud_sql_instance()
    elif command == 'gke':
        gcp_manager.create_kubernetes_cluster()
    elif command == 'cloudrun':
        gcp_manager.create_cloud_run_service()
    elif command == 'status':
        print("Instance Status:")
        print(gcp_manager.get_instance_status())
        print("\nStorage Status:")
        print(gcp_manager.get_storage_status())
        print("\nSQL Status:")
        print(gcp_manager.get_sql_status())
    elif command == 'info':
        info = gcp_manager.get_google_cloud_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
