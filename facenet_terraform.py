#!/usr/bin/env python3
"""
FaceNet Terraform Support

This script provides Terraform support for FaceNet service.
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

class FaceNetTerraform:
    """Terraform manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Terraform manager."""
        self.working_directory = 'terraform'
        self.state_file = 'terraform.tfstate'
        self.plan_file = 'terraform.tfplan'
        self.variables_file = 'variables.tf'
        self.main_file = 'main.tf'
        self.outputs_file = 'outputs.tf'
    
    def create_terraform_directory(self) -> bool:
        """Create Terraform working directory."""
        try:
            print("Creating Terraform directory...")
            
            os.makedirs(self.working_directory, exist_ok=True)
            
            print(f"✓ Terraform directory {self.working_directory} created")
            return True
        except Exception as e:
            print(f"✗ Error creating Terraform directory: {e}")
            return False
    
    def create_variables_file(self) -> bool:
        """Create Terraform variables file."""
        try:
            print("Creating Terraform variables file...")
            
            variables_content = """# FaceNet Terraform Variables

variable "project_name" {
  description = "Name of the project"
  type        = string
  default     = "facenet"
}

variable "environment" {
  description = "Environment name"
  type        = string
  default     = "dev"
}

variable "region" {
  description = "AWS region"
  type        = string
  default     = "us-east-1"
}

variable "availability_zones" {
  description = "Availability zones"
  type        = list(string)
  default     = ["us-east-1a", "us-east-1b"]
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.medium"
}

variable "min_size" {
  description = "Minimum number of instances"
  type        = number
  default     = 1
}

variable "max_size" {
  description = "Maximum number of instances"
  type        = number
  default     = 3
}

variable "desired_capacity" {
  description = "Desired number of instances"
  type        = number
  default     = 2
}

variable "db_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t3.micro"
}

variable "db_allocated_storage" {
  description = "RDS allocated storage"
  type        = number
  default     = 20
}

variable "db_engine_version" {
  description = "RDS engine version"
  type        = string
  default     = "8.0.35"
}

variable "db_username" {
  description = "RDS master username"
  type        = string
  default     = "admin"
}

variable "db_password" {
  description = "RDS master password"
  type        = string
  default     = "adminpassword123"
  sensitive   = true
}

variable "allowed_cidr_blocks" {
  description = "CIDR blocks allowed to access the infrastructure"
  type        = list(string)
  default     = ["0.0.0.0/0"]
}

variable "tags" {
  description = "Tags to apply to resources"
  type        = map(string)
  default = {
    Project     = "FaceNet"
    Environment = "dev"
    ManagedBy   = "Terraform"
  }
}
"""
            
            file_path = os.path.join(self.working_directory, self.variables_file)
            with open(file_path, 'w') as f:
                f.write(variables_content)
            
            print(f"✓ Variables file {self.variables_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating variables file: {e}")
            return False
    
    def create_main_file(self) -> bool:
        """Create Terraform main file."""
        try:
            print("Creating Terraform main file...")
            
            main_content = """# FaceNet Terraform Main Configuration

terraform {
  required_version = ">= 1.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = var.region
  
  default_tags {
    tags = var.tags
  }
}

# Data sources
data "aws_availability_zones" "available" {
  state = "available"
}

data "aws_ami" "amazon_linux" {
  most_recent = true
  owners      = ["amazon"]
  
  filter {
    name   = "name"
    values = ["amzn2-ami-hvm-*-x86_64-gp2"]
  }
}

# VPC
resource "aws_vpc" "main" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true
  
  tags = {
    Name = "${var.project_name}-vpc"
  }
}

# Internet Gateway
resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id
  
  tags = {
    Name = "${var.project_name}-igw"
  }
}

# Public Subnets
resource "aws_subnet" "public" {
  count = length(var.availability_zones)
  
  vpc_id                  = aws_vpc.main.id
  cidr_block              = "10.0.${count.index + 1}.0/24"
  availability_zone       = var.availability_zones[count.index]
  map_public_ip_on_launch = true
  
  tags = {
    Name = "${var.project_name}-public-subnet-${count.index + 1}"
  }
}

# Private Subnets
resource "aws_subnet" "private" {
  count = length(var.availability_zones)
  
  vpc_id            = aws_vpc.main.id
  cidr_block        = "10.0.${count.index + 10}.0/24"
  availability_zone = var.availability_zones[count.index]
  
  tags = {
    Name = "${var.project_name}-private-subnet-${count.index + 1}"
  }
}

# Route Table for Public Subnets
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id
  
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }
  
  tags = {
    Name = "${var.project_name}-public-rt"
  }
}

# Route Table Association for Public Subnets
resource "aws_route_table_association" "public" {
  count = length(aws_subnet.public)
  
  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

# Security Group for Web Servers
resource "aws_security_group" "web" {
  name_prefix = "${var.project_name}-web-"
  vpc_id      = aws_vpc.main.id
  
  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
  }
  
  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
  }
  
  ingress {
    from_port   = 8080
    to_port     = 8080
    protocol    = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
  }
  
  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
  }
  
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  
  tags = {
    Name = "${var.project_name}-web-sg"
  }
}

# Security Group for Database
resource "aws_security_group" "database" {
  name_prefix = "${var.project_name}-db-"
  vpc_id      = aws_vpc.main.id
  
  ingress {
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.web.id]
  }
  
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  
  tags = {
    Name = "${var.project_name}-db-sg"
  }
}

# Application Load Balancer
resource "aws_lb" "main" {
  name               = "${var.project_name}-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.web.id]
  subnets            = aws_subnet.public[*].id
  
  enable_deletion_protection = false
  
  tags = {
    Name = "${var.project_name}-alb"
  }
}

# Target Group
resource "aws_lb_target_group" "main" {
  name     = "${var.project_name}-tg"
  port     = 8080
  protocol = "HTTP"
  vpc_id   = aws_vpc.main.id
  
  health_check {
    enabled             = true
    healthy_threshold   = 2
    unhealthy_threshold = 2
    timeout             = 5
    interval            = 30
    path                = "/health"
    matcher             = "200"
    port                = "traffic-port"
    protocol            = "HTTP"
  }
  
  tags = {
    Name = "${var.project_name}-tg"
  }
}

# Launch Template
resource "aws_launch_template" "main" {
  name_prefix   = "${var.project_name}-"
  image_id      = data.aws_ami.amazon_linux.id
  instance_type = var.instance_type
  
  vpc_security_group_ids = [aws_security_group.web.id]
  
  user_data = base64encode(templatefile("${path.module}/user_data.sh", {
    project_name = var.project_name
  }))
  
  tag_specifications {
    resource_type = "instance"
    tags = {
      Name = "${var.project_name}-instance"
    }
  }
  
  tags = {
    Name = "${var.project_name}-lt"
  }
}

# Auto Scaling Group
resource "aws_autoscaling_group" "main" {
  name                = "${var.project_name}-asg"
  vpc_zone_identifier = aws_subnet.public[*].id
  target_group_arns   = [aws_lb_target_group.main.arn]
  health_check_type   = "ELB"
  health_check_grace_period = 300
  
  min_size         = var.min_size
  max_size         = var.max_size
  desired_capacity = var.desired_capacity
  
  launch_template {
    id      = aws_launch_template.main.id
    version = "$Latest"
  }
  
  tag {
    key                 = "Name"
    value               = "${var.project_name}-asg"
    propagate_at_launch = false
  }
}

# Load Balancer Listener
resource "aws_lb_listener" "main" {
  load_balancer_arn = aws_lb.main.arn
  port              = "80"
  protocol          = "HTTP"
  
  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.main.arn
  }
}

# RDS Subnet Group
resource "aws_db_subnet_group" "main" {
  name       = "${var.project_name}-db-subnet-group"
  subnet_ids = aws_subnet.private[*].id
  
  tags = {
    Name = "${var.project_name}-db-subnet-group"
  }
}

# RDS Instance
resource "aws_db_instance" "main" {
  identifier = "${var.project_name}-db"
  
  engine         = "mysql"
  engine_version = var.db_engine_version
  instance_class = var.db_instance_class
  
  allocated_storage     = var.db_allocated_storage
  max_allocated_storage = 100
  storage_type          = "gp2"
  storage_encrypted     = true
  
  db_name  = "facenet_db"
  username = var.db_username
  password = var.db_password
  
  vpc_security_group_ids = [aws_security_group.database.id]
  db_subnet_group_name   = aws_db_subnet_group.main.name
  
  backup_retention_period = 7
  backup_window          = "03:00-04:00"
  maintenance_window     = "sun:04:00-sun:05:00"
  
  skip_final_snapshot = true
  deletion_protection = false
  
  tags = {
    Name = "${var.project_name}-db"
  }
}

# S3 Bucket for Models
resource "aws_s3_bucket" "models" {
  bucket = "${var.project_name}-models-${random_string.bucket_suffix.result}"
  
  tags = {
    Name = "${var.project_name}-models"
  }
}

resource "random_string" "bucket_suffix" {
  length  = 8
  special = false
  upper   = false
}

resource "aws_s3_bucket_versioning" "models" {
  bucket = aws_s3_bucket.models.id
  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "models" {
  bucket = aws_s3_bucket.models.id
  
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# CloudWatch Log Group
resource "aws_cloudwatch_log_group" "main" {
  name              = "/aws/ec2/${var.project_name}"
  retention_in_days = 7
  
  tags = {
    Name = "${var.project_name}-logs"
  }
}
"""
            
            file_path = os.path.join(self.working_directory, self.main_file)
            with open(file_path, 'w') as f:
                f.write(main_content)
            
            print(f"✓ Main file {self.main_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating main file: {e}")
            return False
    
    def create_outputs_file(self) -> bool:
        """Create Terraform outputs file."""
        try:
            print("Creating Terraform outputs file...")
            
            outputs_content = """# FaceNet Terraform Outputs

output "vpc_id" {
  description = "ID of the VPC"
  value       = aws_vpc.main.id
}

output "vpc_cidr_block" {
  description = "CIDR block of the VPC"
  value       = aws_vpc.main.cidr_block
}

output "public_subnet_ids" {
  description = "IDs of the public subnets"
  value       = aws_subnet.public[*].id
}

output "private_subnet_ids" {
  description = "IDs of the private subnets"
  value       = aws_subnet.private[*].id
}

output "load_balancer_dns_name" {
  description = "DNS name of the load balancer"
  value       = aws_lb.main.dns_name
}

output "load_balancer_zone_id" {
  description = "Zone ID of the load balancer"
  value       = aws_lb.main.zone_id
}

output "database_endpoint" {
  description = "RDS instance endpoint"
  value       = aws_db_instance.main.endpoint
}

output "database_port" {
  description = "RDS instance port"
  value       = aws_db_instance.main.port
}

output "s3_bucket_name" {
  description = "Name of the S3 bucket for models"
  value       = aws_s3_bucket.models.bucket
}

output "s3_bucket_arn" {
  description = "ARN of the S3 bucket for models"
  value       = aws_s3_bucket.models.arn
}

output "security_group_web_id" {
  description = "ID of the web security group"
  value       = aws_security_group.web.id
}

output "security_group_database_id" {
  description = "ID of the database security group"
  value       = aws_security_group.database.id
}

output "autoscaling_group_name" {
  description = "Name of the Auto Scaling Group"
  value       = aws_autoscaling_group.main.name
}

output "autoscaling_group_arn" {
  description = "ARN of the Auto Scaling Group"
  value       = aws_autoscaling_group.main.arn
}
"""
            
            file_path = os.path.join(self.working_directory, self.outputs_file)
            with open(file_path, 'w') as f:
                f.write(outputs_content)
            
            print(f"✓ Outputs file {self.outputs_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating outputs file: {e}")
            return False
    
    def create_user_data_script(self) -> bool:
        """Create user data script for EC2 instances."""
        try:
            print("Creating user data script...")
            
            user_data_content = """#!/bin/bash
# FaceNet User Data Script

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

# Create application directory
mkdir -p /var/www/html/facenet
cd /var/www/html/facenet

# Download FaceNet models from S3
aws s3 sync s3://${project_name}-models-${random_string.bucket_suffix.result}/ /var/www/html/facenet/models/

# Set permissions
chown -R apache:apache /var/www/html/facenet
chmod -R 755 /var/www/html/facenet

# Create systemd service
cat > /etc/systemd/system/facenet.service << EOF
[Unit]
Description=FaceNet Service
After=network.target
Wants=network.target

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
            
            file_path = os.path.join(self.working_directory, 'user_data.sh')
            with open(file_path, 'w') as f:
                f.write(user_data_content)
            
            print("✓ User data script created")
            return True
        except Exception as e:
            print(f"✗ Error creating user data script: {e}")
            return False
    
    def create_terraform_files(self) -> bool:
        """Create all Terraform files."""
        try:
            print("Creating Terraform files...")
            
            if not self.create_terraform_directory():
                return False
            
            if not self.create_variables_file():
                return False
            
            if not self.create_main_file():
                return False
            
            if not self.create_outputs_file():
                return False
            
            if not self.create_user_data_script():
                return False
            
            print("✓ All Terraform files created")
            return True
        except Exception as e:
            print(f"✗ Error creating Terraform files: {e}")
            return False
    
    def terraform_init(self) -> bool:
        """Initialize Terraform."""
        try:
            print("Initializing Terraform...")
            
            result = subprocess.run([
                'terraform', 'init'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error initializing Terraform: {result.stderr}")
                return False
            
            print("✓ Terraform initialized")
            return True
        except Exception as e:
            print(f"✗ Error initializing Terraform: {e}")
            return False
    
    def terraform_plan(self) -> bool:
        """Create Terraform plan."""
        try:
            print("Creating Terraform plan...")
            
            result = subprocess.run([
                'terraform', 'plan', '-out', self.plan_file
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating Terraform plan: {result.stderr}")
                return False
            
            print("✓ Terraform plan created")
            return True
        except Exception as e:
            print(f"✗ Error creating Terraform plan: {e}")
            return False
    
    def terraform_apply(self) -> bool:
        """Apply Terraform configuration."""
        try:
            print("Applying Terraform configuration...")
            
            result = subprocess.run([
                'terraform', 'apply', '-auto-approve'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error applying Terraform: {result.stderr}")
                return False
            
            print("✓ Terraform configuration applied")
            return True
        except Exception as e:
            print(f"✗ Error applying Terraform: {e}")
            return False
    
    def terraform_destroy(self) -> bool:
        """Destroy Terraform infrastructure."""
        try:
            print("Destroying Terraform infrastructure...")
            
            result = subprocess.run([
                'terraform', 'destroy', '-auto-approve'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error destroying Terraform: {result.stderr}")
                return False
            
            print("✓ Terraform infrastructure destroyed")
            return True
        except Exception as e:
            print(f"✗ Error destroying Terraform: {e}")
            return False
    
    def terraform_output(self) -> Dict:
        """Get Terraform outputs."""
        try:
            result = subprocess.run([
                'terraform', 'output', '-json'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return json.loads(result.stdout)
        except Exception as e:
            return {'error': str(e)}
    
    def terraform_show(self) -> str:
        """Show Terraform state."""
        try:
            result = subprocess.run([
                'terraform', 'show'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                return f"Error: {result.stderr}"
            
            return result.stdout
        except Exception as e:
            return f"Error: {e}"
    
    def deploy_with_terraform(self) -> bool:
        """Deploy FaceNet with Terraform."""
        try:
            print("Deploying FaceNet with Terraform...")
            
            # Create Terraform files
            if not self.create_terraform_files():
                return False
            
            # Initialize Terraform
            if not self.terraform_init():
                return False
            
            # Create plan
            if not self.terraform_plan():
                return False
            
            # Apply configuration
            if not self.terraform_apply():
                return False
            
            print("✓ FaceNet deployed with Terraform successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying with Terraform: {e}")
            return False
    
    def get_terraform_info(self) -> Dict:
        """Get comprehensive Terraform information."""
        try:
            info = {
                'working_directory': self.working_directory,
                'state_file': self.state_file,
                'plan_file': self.plan_file,
                'outputs': self.terraform_output(),
                'state': self.terraform_show()
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Terraform Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_terraform.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet with Terraform")
        print("  init        - Initialize Terraform")
        print("  plan        - Create Terraform plan")
        print("  apply       - Apply Terraform configuration")
        print("  destroy     - Destroy Terraform infrastructure")
        print("  output      - Show Terraform outputs")
        print("  show        - Show Terraform state")
        print("  info        - Show Terraform information")
        return
    
    command = sys.argv[1]
    terraform_manager = FaceNetTerraform()
    
    if command == 'deploy':
        terraform_manager.deploy_with_terraform()
    elif command == 'init':
        terraform_manager.terraform_init()
    elif command == 'plan':
        terraform_manager.terraform_plan()
    elif command == 'apply':
        terraform_manager.terraform_apply()
    elif command == 'destroy':
        terraform_manager.terraform_destroy()
    elif command == 'output':
        outputs = terraform_manager.terraform_output()
        print(json.dumps(outputs, indent=2))
    elif command == 'show':
        state = terraform_manager.terraform_show()
        print(state)
    elif command == 'info':
        info = terraform_manager.get_terraform_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
