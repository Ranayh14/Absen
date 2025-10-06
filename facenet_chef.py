#!/usr/bin/env python3
"""
FaceNet Chef Support

This script provides Chef support for FaceNet service.
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

class FaceNetChef:
    """Chef manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Chef manager."""
        self.working_directory = 'chef'
        self.cookbook_name = 'facenet'
        self.recipe_name = 'default'
        self.attributes_file = 'attributes/default.rb'
        self.recipe_file = 'recipes/default.rb'
        self.templates_directory = 'templates/default'
        self.files_directory = 'files/default'
        self.metadata_file = 'metadata.rb'
        self.berksfile = 'Berksfile'
        self.knife_config = 'knife.rb'
        self.chef_repo = 'chef-repo'
    
    def create_chef_directory(self) -> bool:
        """Create Chef working directory."""
        try:
            print("Creating Chef directory...")
            
            os.makedirs(self.working_directory, exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, self.chef_repo), exist_ok=True)
            
            print(f"✓ Chef directory {self.working_directory} created")
            return True
        except Exception as e:
            print(f"✗ Error creating Chef directory: {e}")
            return False
    
    def create_cookbook_structure(self) -> bool:
        """Create cookbook directory structure."""
        try:
            print("Creating cookbook structure...")
            
            cookbook_path = os.path.join(self.working_directory, self.chef_repo, 'cookbooks', self.cookbook_name)
            
            # Create cookbook directories
            for subdir in ['recipes', 'attributes', 'templates', 'files', 'libraries', 'resources', 'providers', 'test']:
                os.makedirs(os.path.join(cookbook_path, subdir), exist_ok=True)
            
            # Create templates subdirectories
            os.makedirs(os.path.join(cookbook_path, 'templates', 'default'), exist_ok=True)
            
            # Create files subdirectories
            os.makedirs(os.path.join(cookbook_path, 'files', 'default'), exist_ok=True)
            
            print(f"✓ Cookbook structure created at {cookbook_path}")
            return True
        except Exception as e:
            print(f"✗ Error creating cookbook structure: {e}")
            return False
    
    def create_metadata_file(self) -> bool:
        """Create cookbook metadata file."""
        try:
            print("Creating cookbook metadata file...")
            
            metadata_content = """name 'facenet'
maintainer 'FaceNet Team'
maintainer_email 'team@facenet.com'
license 'MIT'
description 'Installs and configures FaceNet service'
long_description 'Installs and configures FaceNet service for face recognition'
version '1.0.0'
chef_version '>= 15.0'

supports 'redhat'
supports 'centos'
supports 'amazon'

depends 'python', '~> 4.0'
depends 'apache2', '~> 8.0'
depends 'mysql', '~> 8.0'
depends 'firewall', '~> 2.0'
depends 'logrotate', '~> 2.0'
depends 'cron', '~> 6.0'

source_url 'https://github.com/facenet/chef-cookbook'
issues_url 'https://github.com/facenet/chef-cookbook/issues'
"""
            
            cookbook_path = os.path.join(self.working_directory, self.chef_repo, 'cookbooks', self.cookbook_name)
            file_path = os.path.join(cookbook_path, self.metadata_file)
            with open(file_path, 'w') as f:
                f.write(metadata_content)
            
            print(f"✓ Metadata file {self.metadata_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating metadata file: {e}")
            return False
    
    def create_attributes_file(self) -> bool:
        """Create cookbook attributes file."""
        try:
            print("Creating cookbook attributes file...")
            
            attributes_content = """# FaceNet Cookbook Attributes

# Project settings
default['facenet']['project_name'] = 'facenet'
default['facenet']['environment'] = 'dev'
default['facenet']['version'] = '1.0.0'

# System settings
default['facenet']['user'] = 'facenet'
default['facenet']['group'] = 'facenet'
default['facenet']['home'] = '/opt/facenet'
default['facenet']['log_dir'] = '/var/log/facenet'
default['facenet']['data_dir'] = '/opt/facenet/data'
default['facenet']['models_dir'] = '/opt/facenet/models'

# Application settings
default['facenet']['app']['port'] = 8080
default['facenet']['app']['host'] = '0.0.0.0'
default['facenet']['app']['debug'] = true
default['facenet']['app']['log_level'] = 'INFO'

# FaceNet settings
default['facenet']['face_crop_size'] = 160
default['facenet']['face_crop_margin'] = 32
default['facenet']['recognition_threshold'] = 1.0
default['facenet']['normalize_embeddings'] = true
default['facenet']['recognition_method'] = 'euclidean'

# Database settings
default['facenet']['database']['host'] = 'localhost'
default['facenet']['database']['port'] = 3306
default['facenet']['database']['name'] = 'facenet_db'
default['facenet']['database']['user'] = 'admin'
default['facenet']['database']['password'] = 'adminpassword123'

# Python settings
default['facenet']['python']['version'] = '3.8'
default['facenet']['python']['pip_requirements'] = [
  'tensorflow==1.7',
  'numpy',
  'scipy',
  'scikit-learn',
  'opencv-python',
  'pillow',
  'h5py',
  'matplotlib',
  'requests',
  'psutil',
  'mysql-connector-python'
]

# Apache settings
default['facenet']['apache']['document_root'] = '/var/www/html'
default['facenet']['apache']['server_name'] = 'facenet.local'
default['facenet']['apache']['ssl_enabled'] = false
default['facenet']['apache']['ssl_cert'] = '/etc/ssl/certs/facenet.crt'
default['facenet']['apache']['ssl_key'] = '/etc/ssl/private/facenet.key'

# Service settings
default['facenet']['service']['name'] = 'facenet'
default['facenet']['service']['description'] = 'FaceNet Service'
default['facenet']['service']['user'] = 'apache'
default['facenet']['service']['group'] = 'apache'
default['facenet']['service']['working_directory'] = '/var/www/html/facenet'
default['facenet']['service']['executable'] = '/usr/bin/python3'
default['facenet']['service']['script'] = 'facenet_service.py'
default['facenet']['service']['restart'] = 'always'
default['facenet']['service']['restart_sec'] = 10

# Logging settings
default['facenet']['logging']['file'] = 'facenet.log'
default['facenet']['logging']['max_size'] = '10MB'
default['facenet']['logging']['backup_count'] = 5
default['facenet']['logging']['level'] = 'INFO'

# Monitoring settings
default['facenet']['monitoring']['enabled'] = true
default['facenet']['monitoring']['health_check_path'] = '/health'
default['facenet']['monitoring']['health_check_interval'] = 30
default['facenet']['monitoring']['metrics_enabled'] = true
default['facenet']['monitoring']['metrics_port'] = 9090

# Security settings
default['facenet']['security']['firewall_enabled'] = true
default['facenet']['security']['allowed_ports'] = [22, 80, 443, 8080, 9090]
default['facenet']['security']['ssl_enabled'] = false

# Backup settings
default['facenet']['backup']['enabled'] = true
default['facenet']['backup']['directory'] = '/opt/backups'
default['facenet']['backup']['retention_days'] = 7
default['facenet']['backup']['schedule'] = '0 2 * * *'

# Update settings
default['facenet']['update']['auto_update'] = false
default['facenet']['update']['schedule'] = '0 3 * * 0'
"""
            
            cookbook_path = os.path.join(self.working_directory, self.chef_repo, 'cookbooks', self.cookbook_name)
            file_path = os.path.join(cookbook_path, self.attributes_file)
            with open(file_path, 'w') as f:
                f.write(attributes_content)
            
            print(f"✓ Attributes file {self.attributes_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating attributes file: {e}")
            return False
    
    def create_recipe_file(self) -> bool:
        """Create cookbook recipe file."""
        try:
            print("Creating cookbook recipe file...")
            
            recipe_content = """# FaceNet Cookbook Recipe

# Update system packages
package 'Update system packages' do
  package_name %w(yum-utils)
  action :install
end

execute 'yum update' do
  command 'yum update -y'
  action :run
end

# Install system dependencies
package 'Install system dependencies' do
  package_name %w(
    python3
    python3-pip
    gcc
    gcc-c++
    make
    cmake
    opencv-devel
    gtk3-devel
    mysql-devel
    httpd
    php
    php-mysql
    php-curl
  )
  action :install
end

# Install Python dependencies
node['facenet']['python']['pip_requirements'].each do |package|
  python_pip package do
    action :install
  end
end

# Create system user
user node['facenet']['user'] do
  group node['facenet']['group']
  home node['facenet']['home']
  shell '/bin/bash'
  system true
  action :create
end

group node['facenet']['group'] do
  members [node['facenet']['user']]
  action :create
end

# Create application directories
[node['facenet']['home'], node['facenet']['log_dir'], node['facenet']['data_dir'], node['facenet']['models_dir']].each do |dir|
  directory dir do
    owner node['facenet']['user']
    group node['facenet']['group']
    mode '0755'
    recursive true
    action :create
  end
end

# Download FaceNet models
remote_file "#{node['facenet']['models_dir']}/facenet_keras.h5" do
  source 'https://github.com/nyoki-mtl/keras-facenet/releases/download/v0.0.1/facenet_keras.h5'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0644'
  action :create
end

remote_file "#{node['facenet']['models_dir']}/mtcnn_weights.npz" do
  source 'https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.npz'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0644'
  action :create
end

# Configure Apache
template '/etc/httpd/conf/httpd.conf' do
  source 'httpd.conf.erb'
  owner 'root'
  group 'root'
  mode '0644'
  variables(
    document_root: node['facenet']['apache']['document_root'],
    server_name: node['facenet']['apache']['server_name']
  )
  notifies :restart, 'service[httpd]', :immediately
end

# Configure PHP
template '/etc/php.ini' do
  source 'php.ini.erb'
  owner 'root'
  group 'root'
  mode '0644'
  notifies :restart, 'service[httpd]', :immediately
end

# Start and enable Apache
service 'httpd' do
  action [:start, :enable]
end

# Create FaceNet configuration
template "#{node['facenet']['home']}/facenet_config.py" do
  source 'facenet_config.py.erb'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0644'
  variables(
    face_crop_size: node['facenet']['face_crop_size'],
    face_crop_margin: node['facenet']['face_crop_margin'],
    recognition_threshold: node['facenet']['recognition_threshold'],
    normalize_embeddings: node['facenet']['normalize_embeddings'],
    recognition_method: node['facenet']['recognition_method']
  )
end

# Create FaceNet database configuration
template "#{node['facenet']['home']}/facenet_database.py" do
  source 'facenet_database.py.erb'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0644'
  variables(
    db_host: node['facenet']['database']['host'],
    db_port: node['facenet']['database']['port'],
    db_name: node['facenet']['database']['name'],
    db_user: node['facenet']['database']['user'],
    db_password: node['facenet']['database']['password']
  )
end

# Create FaceNet service script
template "#{node['facenet']['home']}/facenet_service.py" do
  source 'facenet_service.py.erb'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0755'
end

# Create FaceNet CLI script
template "#{node['facenet']['home']}/facenet_cli.py" do
  source 'facenet_cli.py.erb'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0755'
end

# Create FaceNet API script
template "#{node['facenet']['home']}/facenet_api.php" do
  source 'facenet_api.php.erb'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0644'
end

# Create main application file
template "#{node['facenet']['home']}/index.php" do
  source 'index.php.erb'
  owner node['facenet']['user']
  group node['facenet']['group']
  mode '0644'
end

# Create systemd service file
template "/etc/systemd/system/#{node['facenet']['service']['name']}.service" do
  source 'facenet.service.erb'
  owner 'root'
  group 'root'
  mode '0644'
  variables(
    service_name: node['facenet']['service']['name'],
    service_description: node['facenet']['service']['description'],
    service_user: node['facenet']['service']['user'],
    service_group: node['facenet']['service']['group'],
    service_working_directory: node['facenet']['service']['working_directory'],
    service_executable: node['facenet']['service']['executable'],
    service_script: node['facenet']['service']['script'],
    service_restart: node['facenet']['service']['restart'],
    service_restart_sec: node['facenet']['service']['restart_sec']
  )
  notifies :restart, "service[#{node['facenet']['service']['name']}]", :immediately
end

# Start and enable FaceNet service
service node['facenet']['service']['name'] do
  action [:start, :enable]
end

# Configure firewall
if node['facenet']['security']['firewall_enabled']
  node['facenet']['security']['allowed_ports'].each do |port|
    firewall_rule "allow_port_#{port}" do
      port port
      protocol :tcp
      action :allow
    end
  end
end

# Create log rotation configuration
template "/etc/logrotate.d/#{node['facenet']['service']['name']}" do
  source 'facenet.logrotate.erb'
  owner 'root'
  group 'root'
  mode '0644'
  variables(
    log_file: "#{node['facenet']['log_dir']}/#{node['facenet']['logging']['file']}",
    max_size: node['facenet']['logging']['max_size'],
    backup_count: node['facenet']['logging']['backup_count']
  )
end

# Create monitoring script
if node['facenet']['monitoring']['enabled']
  template '/opt/scripts/monitor.sh' do
    source 'monitor.sh.erb'
    owner 'root'
    group 'root'
    mode '0755'
    variables(
      service_name: node['facenet']['service']['name'],
      health_check_path: node['facenet']['monitoring']['health_check_path'],
      health_check_interval: node['facenet']['monitoring']['health_check_interval']
    )
  end
  
  cron 'FaceNet Monitoring' do
    minute '*/5'
    command '/opt/scripts/monitor.sh'
    user 'root'
  end
end

# Create backup script
if node['facenet']['backup']['enabled']
  template '/opt/scripts/backup.sh' do
    source 'backup.sh.erb'
    owner 'root'
    group 'root'
    mode '0755'
    variables(
      backup_directory: node['facenet']['backup']['directory'],
      retention_days: node['facenet']['backup']['retention_days']
    )
  end
  
  cron 'FaceNet Backup' do
    minute '0'
    hour '2'
    command '/opt/scripts/backup.sh'
    user 'root'
  end
end
"""
            
            cookbook_path = os.path.join(self.working_directory, self.chef_repo, 'cookbooks', self.cookbook_name)
            file_path = os.path.join(cookbook_path, self.recipe_file)
            with open(file_path, 'w') as f:
                f.write(recipe_content)
            
            print(f"✓ Recipe file {self.recipe_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating recipe file: {e}")
            return False
    
    def create_berksfile(self) -> bool:
        """Create Berksfile for cookbook dependencies."""
        try:
            print("Creating Berksfile...")
            
            berksfile_content = """# FaceNet Cookbook Berksfile

source 'https://supermarket.chef.io'

cookbook 'python', '~> 4.0'
cookbook 'apache2', '~> 8.0'
cookbook 'mysql', '~> 8.0'
cookbook 'firewall', '~> 2.0'
cookbook 'logrotate', '~> 2.0'
cookbook 'cron', '~> 6.0'

metadata
"""
            
            cookbook_path = os.path.join(self.working_directory, self.chef_repo, 'cookbooks', self.cookbook_name)
            file_path = os.path.join(cookbook_path, self.berksfile)
            with open(file_path, 'w') as f:
                f.write(berksfile_content)
            
            print(f"✓ Berksfile created")
            return True
        except Exception as e:
            print(f"✗ Error creating Berksfile: {e}")
            return False
    
    def create_knife_config(self) -> bool:
        """Create knife configuration file."""
        try:
            print("Creating knife configuration...")
            
            knife_config_content = """# FaceNet Chef Knife Configuration

current_dir = File.dirname(__FILE__)
log_level                :info
log_location             STDOUT
node_name                'facenet-admin'
client_key               "#{current_dir}/.chef/facenet-admin.pem"
chef_server_url          'https://chef-server.example.com/organizations/facenet'
cookbook_path            ["#{current_dir}/cookbooks"]
cache_type               'BasicFile'
cache_options( :path => "#{current_dir}/.chef/checksums" )
"""
            
            file_path = os.path.join(self.working_directory, self.knife_config)
            with open(file_path, 'w') as f:
                f.write(knife_config_content)
            
            print(f"✓ Knife configuration created")
            return True
        except Exception as e:
            print(f"✗ Error creating knife configuration: {e}")
            return False
    
    def create_chef_files(self) -> bool:
        """Create all Chef files."""
        try:
            print("Creating Chef files...")
            
            if not self.create_chef_directory():
                return False
            
            if not self.create_cookbook_structure():
                return False
            
            if not self.create_metadata_file():
                return False
            
            if not self.create_attributes_file():
                return False
            
            if not self.create_recipe_file():
                return False
            
            if not self.create_berksfile():
                return False
            
            if not self.create_knife_config():
                return False
            
            print("✓ All Chef files created")
            return True
        except Exception as e:
            print(f"✗ Error creating Chef files: {e}")
            return False
    
    def berks_install(self) -> bool:
        """Install cookbook dependencies using Berkshelf."""
        try:
            print("Installing cookbook dependencies...")
            
            cookbook_path = os.path.join(self.working_directory, self.chef_repo, 'cookbooks', self.cookbook_name)
            
            result = subprocess.run([
                'berks', 'install'
            ], cwd=cookbook_path, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error installing dependencies: {result.stderr}")
                return False
            
            print("✓ Dependencies installed")
            return True
        except Exception as e:
            print(f"✗ Error installing dependencies: {e}")
            return False
    
    def berks_upload(self) -> bool:
        """Upload cookbook to Chef server."""
        try:
            print("Uploading cookbook to Chef server...")
            
            cookbook_path = os.path.join(self.working_directory, self.chef_repo, 'cookbooks', self.cookbook_name)
            
            result = subprocess.run([
                'berks', 'upload'
            ], cwd=cookbook_path, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error uploading cookbook: {result.stderr}")
                return False
            
            print("✓ Cookbook uploaded")
            return True
        except Exception as e:
            print(f"✗ Error uploading cookbook: {e}")
            return False
    
    def knife_bootstrap(self, node_name: str, ip_address: str) -> bool:
        """Bootstrap a node with Chef."""
        try:
            print(f"Bootstrapping node {node_name} at {ip_address}...")
            
            result = subprocess.run([
                'knife', 'bootstrap', ip_address,
                '--node-name', node_name,
                '--run-list', f"recipe[{self.cookbook_name}]",
                '--sudo'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error bootstrapping node: {result.stderr}")
                return False
            
            print("✓ Node bootstrapped")
            return True
        except Exception as e:
            print(f"✗ Error bootstrapping node: {e}")
            return False
    
    def deploy_with_chef(self) -> bool:
        """Deploy FaceNet with Chef."""
        try:
            print("Deploying FaceNet with Chef...")
            
            # Create Chef files
            if not self.create_chef_files():
                return False
            
            # Install dependencies
            if not self.berks_install():
                return False
            
            # Upload cookbook
            if not self.berks_upload():
                return False
            
            print("✓ FaceNet deployed with Chef successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying with Chef: {e}")
            return False
    
    def get_chef_info(self) -> Dict:
        """Get comprehensive Chef information."""
        try:
            info = {
                'working_directory': self.working_directory,
                'cookbook_name': self.cookbook_name,
                'recipe_name': self.recipe_name,
                'attributes_file': self.attributes_file,
                'recipe_file': self.recipe_file,
                'templates_directory': self.templates_directory,
                'files_directory': self.files_directory,
                'metadata_file': self.metadata_file,
                'berksfile': self.berksfile,
                'knife_config': self.knife_config,
                'chef_repo': self.chef_repo
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Chef Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_chef.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet with Chef")
        print("  install     - Install cookbook dependencies")
        print("  upload      - Upload cookbook to Chef server")
        print("  bootstrap   - Bootstrap a node with Chef")
        print("  info        - Show Chef information")
        return
    
    command = sys.argv[1]
    chef_manager = FaceNetChef()
    
    if command == 'deploy':
        chef_manager.deploy_with_chef()
    elif command == 'install':
        chef_manager.berks_install()
    elif command == 'upload':
        chef_manager.berks_upload()
    elif command == 'bootstrap':
        if len(sys.argv) < 4:
            print("Usage: python facenet_chef.py bootstrap <node_name> <ip_address>")
            return
        node_name = sys.argv[2]
        ip_address = sys.argv[3]
        chef_manager.knife_bootstrap(node_name, ip_address)
    elif command == 'info':
        info = chef_manager.get_chef_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
