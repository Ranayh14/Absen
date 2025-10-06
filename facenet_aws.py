#!/usr/bin/env python3
"""
FaceNet AWS Support

This script provides AWS support for FaceNet service.
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

class FaceNetAWS:
    """AWS manager for FaceNet service."""
    
    def __init__(self):
        """Initialize AWS manager."""
        self.region = 'us-east-1'
        self.stack_name = 'facenet-stack'
        self.bucket_name = 'facenet-models'
        self.instance_type = 't3.medium'
        self.key_name = 'facenet-key'
        self.security_group_name = 'facenet-sg'
    
    def create_ec2_instance(self) -> bool:
        """Create EC2 instance for FaceNet."""
        try:
            print("Creating EC2 instance...")
            
            # Create security group
            if not self.create_security_group():
                return False
            
            # Create key pair
            if not self.create_key_pair():
                return False
            
            # Launch instance
            user_data = self.get_user_data_script()
            
            result = subprocess.run([
                'aws', 'ec2', 'run-instances',
                '--image-id', 'ami-0c02fb55956c7d316',  # Amazon Linux 2
                '--count', '1',
                '--instance-type', self.instance_type,
                '--key-name', self.key_name,
                '--security-groups', self.security_group_name,
                '--user-data', user_data,
                '--tag-specifications', 'ResourceType=instance,Tags=[{Key=Name,Value=FaceNet-Instance}]'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating EC2 instance: {result.stderr}")
                return False
            
            print("✓ EC2 instance created")
            return True
        except Exception as e:
            print(f"✗ Error creating EC2 instance: {e}")
            return False
    
    def create_security_group(self) -> bool:
        """Create security group."""
        try:
            print("Creating security group...")
            
            # Create security group
            result = subprocess.run([
                'aws', 'ec2', 'create-security-group',
                '--group-name', self.security_group_name,
                '--description', 'Security group for FaceNet service'
            ], capture_output=True, text=True)
            
            if result.returncode != 0 and 'already exists' not in result.stderr:
                print(f"✗ Error creating security group: {result.stderr}")
                return False
            
            # Add inbound rules
            rules = [
                {'port': 22, 'protocol': 'tcp', 'cidr': '0.0.0.0/0'},  # SSH
                {'port': 80, 'protocol': 'tcp', 'cidr': '0.0.0.0/0'},  # HTTP
                {'port': 443, 'protocol': 'tcp', 'cidr': '0.0.0.0/0'}, # HTTPS
                {'port': 8080, 'protocol': 'tcp', 'cidr': '0.0.0.0/0'}, # FaceNet API
                {'port': 3306, 'protocol': 'tcp', 'cidr': '0.0.0.0/0'}  # MySQL
            ]
            
            for rule in rules:
                result = subprocess.run([
                    'aws', 'ec2', 'authorize-security-group-ingress',
                    '--group-name', self.security_group_name,
                    '--protocol', rule['protocol'],
                    '--port', str(rule['port']),
                    '--cidr', rule['cidr']
                ], capture_output=True, text=True)
                
                if result.returncode != 0 and 'already exists' not in result.stderr:
                    print(f"⚠ Warning: Error adding rule for port {rule['port']}: {result.stderr}")
            
            print(f"✓ Security group {self.security_group_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating security group: {e}")
            return False
    
    def create_key_pair(self) -> bool:
        """Create key pair."""
        try:
            print("Creating key pair...")
            
            result = subprocess.run([
                'aws', 'ec2', 'create-key-pair',
                '--key-name', self.key_name,
                '--query', 'KeyMaterial',
                '--output', 'text'
            ], capture_output=True, text=True)
            
            if result.returncode != 0 and 'already exists' not in result.stderr:
                print(f"✗ Error creating key pair: {result.stderr}")
                return False
            
            # Save private key
            if result.returncode == 0:
                with open(f'{self.key_name}.pem', 'w') as f:
                    f.write(result.stdout)
                os.chmod(f'{self.key_name}.pem', 0o400)
                print(f"✓ Private key saved to {self.key_name}.pem")
            
            print(f"✓ Key pair {self.key_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating key pair: {e}")
            return False
    
    def get_user_data_script(self) -> str:
        """Get user data script for EC2 instance."""
        return """#!/bin/bash
# FaceNet EC2 User Data Script

# Update system
yum update -y

# Install Python 3.8
yum install -y python3 python3-pip

# Install system dependencies
yum install -y gcc gcc-c++ make cmake
yum install -y opencv-devel gtk3-devel
yum install -y mysql-devel

# Install Python dependencies
pip3 install tensorflow==1.7
pip3 install numpy scipy scikit-learn
pip3 install opencv-python pillow
pip3 install h5py matplotlib requests psutil
pip3 install mysql-connector-python

# Install Apache and PHP
yum install -y httpd php php-mysql php-curl

# Start Apache
systemctl start httpd
systemctl enable httpd

# Install MySQL
yum install -y mysql-server
systemctl start mysqld
systemctl enable mysqld

# Create application directory
mkdir -p /var/www/html/facenet
cd /var/www/html/facenet

# Download FaceNet models (this would be done via S3 or other method)
# For now, create placeholder directories
mkdir -p facenet-master/models
mkdir -p logs debug_images backups

# Set permissions
chown -R apache:apache /var/www/html/facenet
chmod -R 755 /var/www/html/facenet

# Create systemd service
cat > /etc/systemd/system/facenet.service << EOF
[Unit]
Description=FaceNet Service
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=apache
Group=apache
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

# Create health check script
cat > /var/www/html/health.php << EOF
<?php
header('Content-Type: application/json');
echo json_encode(['status' => 'healthy', 'timestamp' => date('Y-m-d H:i:s')]);
?>
EOF

echo "FaceNet setup completed" >> /var/log/facenet-setup.log
"""
    
    def create_s3_bucket(self) -> bool:
        """Create S3 bucket for models."""
        try:
            print("Creating S3 bucket...")
            
            result = subprocess.run([
                'aws', 's3', 'mb', f's3://{self.bucket_name}',
                '--region', self.region
            ], capture_output=True, text=True)
            
            if result.returncode != 0 and 'already exists' not in result.stderr:
                print(f"✗ Error creating S3 bucket: {result.stderr}")
                return False
            
            print(f"✓ S3 bucket {self.bucket_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating S3 bucket: {e}")
            return False
    
    def upload_models_to_s3(self) -> bool:
        """Upload FaceNet models to S3."""
        try:
            print("Uploading models to S3...")
            
            models_dir = 'facenet-master/models'
            if not os.path.exists(models_dir):
                print(f"⚠ Models directory {models_dir} not found")
                return False
            
            result = subprocess.run([
                'aws', 's3', 'sync', models_dir, f's3://{self.bucket_name}/models'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error uploading models: {result.stderr}")
                return False
            
            print("✓ Models uploaded to S3")
            return True
        except Exception as e:
            print(f"✗ Error uploading models: {e}")
            return False
    
    def create_rds_instance(self) -> bool:
        """Create RDS instance for database."""
        try:
            print("Creating RDS instance...")
            
            result = subprocess.run([
                'aws', 'rds', 'create-db-instance',
                '--db-instance-identifier', 'facenet-db',
                '--db-instance-class', 'db.t3.micro',
                '--engine', 'mysql',
                '--engine-version', '8.0.35',
                '--master-username', 'admin',
                '--master-user-password', 'adminpassword123',
                '--allocated-storage', '20',
                '--vpc-security-group-ids', self.security_group_name,
                '--backup-retention-period', '7',
                '--storage-encrypted'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating RDS instance: {result.stderr}")
                return False
            
            print("✓ RDS instance created")
            return True
        except Exception as e:
            print(f"✗ Error creating RDS instance: {e}")
            return False
    
    def create_cloudformation_stack(self) -> bool:
        """Create CloudFormation stack."""
        try:
            print("Creating CloudFormation stack...")
            
            template = self.get_cloudformation_template()
            
            with open('facenet-template.yaml', 'w') as f:
                f.write(template)
            
            result = subprocess.run([
                'aws', 'cloudformation', 'create-stack',
                '--stack-name', self.stack_name,
                '--template-body', 'file://facenet-template.yaml',
                '--capabilities', 'CAPABILITY_IAM'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating CloudFormation stack: {result.stderr}")
                return False
            
            print(f"✓ CloudFormation stack {self.stack_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating CloudFormation stack: {e}")
            return False
    
    def get_cloudformation_template(self) -> str:
        """Get CloudFormation template."""
        return f"""AWSTemplateFormatVersion: '2010-09-09'
Description: 'FaceNet Service Infrastructure'

Parameters:
  InstanceType:
    Type: String
    Default: {self.instance_type}
    Description: EC2 instance type
  
  KeyName:
    Type: String
    Default: {self.key_name}
    Description: EC2 Key Pair name

Resources:
  # Security Group
  FaceNetSecurityGroup:
    Type: AWS::EC2::SecurityGroup
    Properties:
      GroupName: {self.security_group_name}
      GroupDescription: Security group for FaceNet service
      SecurityGroupIngress:
        - IpProtocol: tcp
          FromPort: 22
          ToPort: 22
          CidrIp: 0.0.0.0/0
        - IpProtocol: tcp
          FromPort: 80
          ToPort: 80
          CidrIp: 0.0.0.0/0
        - IpProtocol: tcp
          FromPort: 443
          ToPort: 443
          CidrIp: 0.0.0.0/0
        - IpProtocol: tcp
          FromPort: 8080
          ToPort: 8080
          CidrIp: 0.0.0.0/0

  # EC2 Instance
  FaceNetInstance:
    Type: AWS::EC2::Instance
    Properties:
      ImageId: ami-0c02fb55956c7d316
      InstanceType: !Ref InstanceType
      KeyName: !Ref KeyName
      SecurityGroups:
        - !Ref FaceNetSecurityGroup
      UserData:
        Fn::Base64: !Sub |
          #!/bin/bash
          yum update -y
          yum install -y python3 python3-pip httpd php php-mysql
          pip3 install tensorflow numpy opencv-python pillow
          systemctl start httpd
          systemctl enable httpd
      Tags:
        - Key: Name
          Value: FaceNet-Instance

  # S3 Bucket
  FaceNetBucket:
    Type: AWS::S3::Bucket
    Properties:
      BucketName: {self.bucket_name}
      VersioningConfiguration:
        Status: Enabled
      PublicAccessBlockConfiguration:
        BlockPublicAcls: true
        BlockPublicPolicy: true
        IgnorePublicAcls: true
        RestrictPublicBuckets: true

  # RDS Instance
  FaceNetDatabase:
    Type: AWS::RDS::DBInstance
    Properties:
      DBInstanceIdentifier: facenet-db
      DBInstanceClass: db.t3.micro
      Engine: mysql
      EngineVersion: '8.0.35'
      MasterUsername: admin
      MasterUserPassword: adminpassword123
      AllocatedStorage: 20
      VPCSecurityGroups:
        - !Ref FaceNetSecurityGroup
      BackupRetentionPeriod: 7
      StorageEncrypted: true

Outputs:
  InstanceId:
    Description: EC2 Instance ID
    Value: !Ref FaceNetInstance
  
  PublicIP:
    Description: Public IP address
    Value: !GetAtt FaceNetInstance.PublicIp
  
  BucketName:
    Description: S3 Bucket name
    Value: !Ref FaceNetBucket
  
  DatabaseEndpoint:
    Description: RDS endpoint
    Value: !GetAtt FaceNetDatabase.Endpoint.Address
"""
    
    def get_instance_status(self) -> Dict:
        """Get EC2 instance status."""
        try:
            result = subprocess.run([
                'aws', 'ec2', 'describe-instances',
                '--filters', 'Name=tag:Name,Values=FaceNet-Instance',
                '--query', 'Reservations[*].Instances[*].[InstanceId,State.Name,PublicIpAddress]',
                '--output', 'table'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return {'status': result.stdout}
        except Exception as e:
            return {'error': str(e)}
    
    def get_s3_bucket_status(self) -> Dict:
        """Get S3 bucket status."""
        try:
            result = subprocess.run([
                'aws', 's3', 'ls', f's3://{self.bucket_name}'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return {'status': result.stdout}
        except Exception as e:
            return {'error': str(e)}
    
    def get_rds_status(self) -> Dict:
        """Get RDS instance status."""
        try:
            result = subprocess.run([
                'aws', 'rds', 'describe-db-instances',
                '--db-instance-identifier', 'facenet-db',
                '--query', 'DBInstances[*].[DBInstanceStatus,Endpoint.Address]',
                '--output', 'table'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return {'status': result.stdout}
        except Exception as e:
            return {'error': str(e)}
    
    def deploy_to_aws(self) -> bool:
        """Deploy FaceNet to AWS."""
        try:
            print("Deploying FaceNet to AWS...")
            
            # Create S3 bucket
            if not self.create_s3_bucket():
                return False
            
            # Upload models
            if not self.upload_models_to_s3():
                return False
            
            # Create CloudFormation stack
            if not self.create_cloudformation_stack():
                return False
            
            print("✓ FaceNet deployed to AWS successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying to AWS: {e}")
            return False
    
    def get_aws_info(self) -> Dict:
        """Get comprehensive AWS information."""
        try:
            info = {
                'region': self.region,
                'stack_name': self.stack_name,
                'bucket_name': self.bucket_name,
                'instance_type': self.instance_type,
                'key_name': self.key_name,
                'security_group_name': self.security_group_name,
                'instance_status': self.get_instance_status(),
                's3_status': self.get_s3_bucket_status(),
                'rds_status': self.get_rds_status()
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet AWS Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_aws.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet to AWS")
        print("  instance    - Create EC2 instance")
        print("  s3          - Create S3 bucket")
        print("  rds         - Create RDS instance")
        print("  status      - Show AWS resources status")
        print("  info        - Show AWS information")
        return
    
    command = sys.argv[1]
    aws_manager = FaceNetAWS()
    
    if command == 'deploy':
        aws_manager.deploy_to_aws()
    elif command == 'instance':
        aws_manager.create_ec2_instance()
    elif command == 's3':
        aws_manager.create_s3_bucket()
    elif command == 'rds':
        aws_manager.create_rds_instance()
    elif command == 'status':
        print("Instance Status:")
        print(aws_manager.get_instance_status())
        print("\nS3 Status:")
        print(aws_manager.get_s3_bucket_status())
        print("\nRDS Status:")
        print(aws_manager.get_rds_status())
    elif command == 'info':
        info = aws_manager.get_aws_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
