#!/usr/bin/env python3
"""
FaceNet Puppet Support

This script provides Puppet support for FaceNet service.
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

class FaceNetPuppet:
    """Puppet manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Puppet manager."""
        self.working_directory = 'puppet'
        self.module_name = 'facenet'
        self.manifest_name = 'init.pp'
        self.manifests_directory = 'manifests'
        self.templates_directory = 'templates'
        self.files_directory = 'files'
        self.facts_directory = 'lib/facter'
        self.functions_directory = 'lib/puppet/functions'
        self.types_directory = 'lib/puppet/type'
        self.providers_directory = 'lib/puppet/provider'
        self.metadata_file = 'metadata.json'
        self.puppetfile = 'Puppetfile'
        self.hiera_config = 'hiera.yaml'
        self.hiera_data = 'data'
        self.environment = 'production'
    
    def create_puppet_directory(self) -> bool:
        """Create Puppet working directory."""
        try:
            print("Creating Puppet directory...")
            
            os.makedirs(self.working_directory, exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, 'modules'), exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, 'environments', self.environment), exist_ok=True)
            
            print(f"✓ Puppet directory {self.working_directory} created")
            return True
        except Exception as e:
            print(f"✗ Error creating Puppet directory: {e}")
            return False
    
    def create_module_structure(self) -> bool:
        """Create module directory structure."""
        try:
            print("Creating module structure...")
            
            module_path = os.path.join(self.working_directory, 'modules', self.module_name)
            
            # Create module directories
            for subdir in [self.manifests_directory, self.templates_directory, self.files_directory, 
                          self.facts_directory, self.functions_directory, self.types_directory, 
                          self.providers_directory, 'spec', 'tests']:
                os.makedirs(os.path.join(module_path, subdir), exist_ok=True)
            
            # Create templates subdirectories
            os.makedirs(os.path.join(module_path, self.templates_directory, 'default'), exist_ok=True)
            
            # Create files subdirectories
            os.makedirs(os.path.join(module_path, self.files_directory, 'default'), exist_ok=True)
            
            print(f"✓ Module structure created at {module_path}")
            return True
        except Exception as e:
            print(f"✗ Error creating module structure: {e}")
            return False
    
    def create_metadata_file(self) -> bool:
        """Create module metadata file."""
        try:
            print("Creating module metadata file...")
            
            metadata_content = {
                "name": "facenet-facenet",
                "version": "1.0.0",
                "author": "FaceNet Team",
                "summary": "Installs and configures FaceNet service",
                "license": "MIT",
                "source": "https://github.com/facenet/puppet-module",
                "project_page": "https://github.com/facenet/puppet-module",
                "issues_url": "https://github.com/facenet/puppet-module/issues",
                "dependencies": [
                    {
                        "name": "puppetlabs-stdlib",
                        "version_requirement": ">= 4.0.0 < 7.0.0"
                    },
                    {
                        "name": "puppetlabs-python",
                        "version_requirement": ">= 4.0.0 < 7.0.0"
                    },
                    {
                        "name": "puppetlabs-apache",
                        "version_requirement": ">= 3.0.0 < 7.0.0"
                    },
                    {
                        "name": "puppetlabs-mysql",
                        "version_requirement": ">= 3.0.0 < 7.0.0"
                    },
                    {
                        "name": "puppetlabs-firewall",
                        "version_requirement": ">= 1.0.0 < 3.0.0"
                    }
                ],
                "operatingsystem_support": [
                    {
                        "operatingsystem": "RedHat",
                        "operatingsystemrelease": ["7", "8"]
                    },
                    {
                        "operatingsystem": "CentOS",
                        "operatingsystemrelease": ["7", "8"]
                    },
                    {
                        "operatingsystem": "Amazon",
                        "operatingsystemrelease": ["2"]
                    }
                ],
                "requirements": [
                    {
                        "name": "puppet",
                        "version_requirement": ">= 5.0.0 < 8.0.0"
                    }
                ],
                "tags": ["facenet", "face-recognition", "machine-learning"]
            }
            
            module_path = os.path.join(self.working_directory, 'modules', self.module_name)
            file_path = os.path.join(module_path, self.metadata_file)
            with open(file_path, 'w') as f:
                json.dump(metadata_content, f, indent=2)
            
            print(f"✓ Metadata file {self.metadata_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating metadata file: {e}")
            return False
    
    def create_init_manifest(self) -> bool:
        """Create module init manifest."""
        try:
            print("Creating module init manifest...")
            
            init_manifest_content = """# FaceNet Puppet Module

class facenet (
  String $project_name = 'facenet',
  String $environment = 'dev',
  String $version = '1.0.0',
  String $user = 'facenet',
  String $group = 'facenet',
  String $home = '/opt/facenet',
  String $log_dir = '/var/log/facenet',
  String $data_dir = '/opt/facenet/data',
  String $models_dir = '/opt/facenet/models',
  Integer $app_port = 8080,
  String $app_host = '0.0.0.0',
  Boolean $app_debug = true,
  String $app_log_level = 'INFO',
  Integer $face_crop_size = 160,
  Integer $face_crop_margin = 32,
  Float $recognition_threshold = 1.0,
  Boolean $normalize_embeddings = true,
  String $recognition_method = 'euclidean',
  String $db_host = 'localhost',
  Integer $db_port = 3306,
  String $db_name = 'facenet_db',
  String $db_user = 'admin',
  String $db_password = 'adminpassword123',
  String $python_version = '3.8',
  Array[String] $pip_requirements = [
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
  ],
  String $apache_document_root = '/var/www/html',
  String $apache_server_name = 'facenet.local',
  Boolean $apache_ssl_enabled = false,
  String $service_name = 'facenet',
  String $service_description = 'FaceNet Service',
  String $service_user = 'apache',
  String $service_group = 'apache',
  String $service_working_directory = '/var/www/html/facenet',
  String $service_executable = '/usr/bin/python3',
  String $service_script = 'facenet_service.py',
  String $service_restart = 'always',
  Integer $service_restart_sec = 10,
  String $log_file = 'facenet.log',
  String $log_max_size = '10MB',
  Integer $log_backup_count = 5,
  String $log_level = 'INFO',
  Boolean $monitoring_enabled = true,
  String $health_check_path = '/health',
  Integer $health_check_interval = 30,
  Boolean $metrics_enabled = true,
  Integer $metrics_port = 9090,
  Boolean $firewall_enabled = true,
  Array[Integer] $allowed_ports = [22, 80, 443, 8080, 9090],
  Boolean $backup_enabled = true,
  String $backup_directory = '/opt/backups',
  Integer $backup_retention_days = 7,
  String $backup_schedule = '0 2 * * *'
) {
  
  # Update system packages
  package { 'yum-utils':
    ensure => 'installed',
  }
  
  exec { 'yum update':
    command => 'yum update -y',
    path    => '/usr/bin:/bin',
    require => Package['yum-utils'],
  }
  
  # Install system dependencies
  package { [
    'python3',
    'python3-pip',
    'gcc',
    'gcc-c++',
    'make',
    'cmake',
    'opencv-devel',
    'gtk3-devel',
    'mysql-devel',
    'httpd',
    'php',
    'php-mysql',
    'php-curl'
  ]:
    ensure => 'installed',
    require => Exec['yum update'],
  }
  
  # Install Python dependencies
  $pip_requirements.each |String $package| {
    python::pip { $package:
      ensure => 'present',
      require => Package['python3-pip'],
    }
  }
  
  # Create system user
  user { $user:
    ensure     => 'present',
    gid        => $group,
    home       => $home,
    shell      => '/bin/bash',
    system     => true,
    managehome => true,
  }
  
  group { $group:
    ensure => 'present',
    members => [$user],
  }
  
  # Create application directories
  [$home, $log_dir, $data_dir, $models_dir].each |String $dir| {
    file { $dir:
      ensure => 'directory',
      owner  => $user,
      group  => $group,
      mode   => '0755',
    }
  }
  
  # Download FaceNet models
  file { "${models_dir}/facenet_keras.h5":
    ensure => 'present',
    source => 'https://github.com/nyoki-mtl/keras-facenet/releases/download/v0.0.1/facenet_keras.h5',
    owner  => $user,
    group  => $group,
    mode   => '0644',
    require => File[$models_dir],
  }
  
  file { "${models_dir}/mtcnn_weights.npz":
    ensure => 'present',
    source => 'https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.npz',
    owner  => $user,
    group  => $group,
    mode   => '0644',
    require => File[$models_dir],
  }
  
  # Configure Apache
  file { '/etc/httpd/conf/httpd.conf':
    ensure  => 'present',
    content => template('facenet/httpd.conf.erb'),
    owner   => 'root',
    group   => 'root',
    mode    => '0644',
    notify  => Service['httpd'],
    require => Package['httpd'],
  }
  
  # Configure PHP
  file { '/etc/php.ini':
    ensure  => 'present',
    content => template('facenet/php.ini.erb'),
    owner   => 'root',
    group   => 'root',
    mode    => '0644',
    notify  => Service['httpd'],
    require => Package['php'],
  }
  
  # Start and enable Apache
  service { 'httpd':
    ensure => 'running',
    enable => true,
    require => Package['httpd'],
  }
  
  # Create FaceNet configuration
  file { "${home}/facenet_config.py":
    ensure  => 'present',
    content => template('facenet/facenet_config.py.erb'),
    owner   => $user,
    group   => $group,
    mode    => '0644',
    require => File[$home],
  }
  
  # Create FaceNet database configuration
  file { "${home}/facenet_database.py":
    ensure  => 'present',
    content => template('facenet/facenet_database.py.erb'),
    owner   => $user,
    group   => $group,
    mode    => '0644',
    require => File[$home],
  }
  
  # Create FaceNet service script
  file { "${home}/facenet_service.py":
    ensure  => 'present',
    content => template('facenet/facenet_service.py.erb'),
    owner   => $user,
    group   => $group,
    mode    => '0755',
    require => File[$home],
  }
  
  # Create FaceNet CLI script
  file { "${home}/facenet_cli.py":
    ensure  => 'present',
    content => template('facenet/facenet_cli.py.erb'),
    owner   => $user,
    group   => $group,
    mode    => '0755',
    require => File[$home],
  }
  
  # Create FaceNet API script
  file { "${home}/facenet_api.php":
    ensure  => 'present',
    content => template('facenet/facenet_api.php.erb'),
    owner   => $user,
    group   => $group,
    mode    => '0644',
    require => File[$home],
  }
  
  # Create main application file
  file { "${home}/index.php":
    ensure  => 'present',
    content => template('facenet/index.php.erb'),
    owner   => $user,
    group   => $group,
    mode    => '0644',
    require => File[$home],
  }
  
  # Create systemd service file
  file { "/etc/systemd/system/${service_name}.service":
    ensure  => 'present',
    content => template('facenet/facenet.service.erb'),
    owner   => 'root',
    group   => 'root',
    mode    => '0644',
    notify  => Service[$service_name],
  }
  
  # Start and enable FaceNet service
  service { $service_name:
    ensure => 'running',
    enable => true,
    require => File["/etc/systemd/system/${service_name}.service"],
  }
  
  # Configure firewall
  if $firewall_enabled {
    $allowed_ports.each |Integer $port| {
      firewall { "allow_port_${port}":
        port   => $port,
        proto  => 'tcp',
        action => 'accept',
      }
    }
  }
  
  # Create log rotation configuration
  file { "/etc/logrotate.d/${service_name}":
    ensure  => 'present',
    content => template('facenet/facenet.logrotate.erb'),
    owner   => 'root',
    group   => 'root',
    mode    => '0644',
  }
  
  # Create monitoring script
  if $monitoring_enabled {
    file { '/opt/scripts/monitor.sh':
      ensure  => 'present',
      content => template('facenet/monitor.sh.erb'),
      owner   => 'root',
      group   => 'root',
      mode    => '0755',
    }
    
    cron { 'FaceNet Monitoring':
      command => '/opt/scripts/monitor.sh',
      minute  => '*/5',
      user    => 'root',
    }
  }
  
  # Create backup script
  if $backup_enabled {
    file { '/opt/scripts/backup.sh':
      ensure  => 'present',
      content => template('facenet/backup.sh.erb'),
      owner   => 'root',
      group   => 'root',
      mode    => '0755',
    }
    
    cron { 'FaceNet Backup':
      command => '/opt/scripts/backup.sh',
      minute  => '0',
      hour    => '2',
      user    => 'root',
    }
  }
}
"""
            
            module_path = os.path.join(self.working_directory, 'modules', self.module_name)
            file_path = os.path.join(module_path, self.manifests_directory, self.manifest_name)
            with open(file_path, 'w') as f:
                f.write(init_manifest_content)
            
            print(f"✓ Init manifest {self.manifest_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating init manifest: {e}")
            return False
    
    def create_puppetfile(self) -> bool:
        """Create Puppetfile for module dependencies."""
        try:
            print("Creating Puppetfile...")
            
            puppetfile_content = """# FaceNet Puppet Module Puppetfile

forge 'https://forge.puppet.com'

mod 'puppetlabs-stdlib', '6.0.0'
mod 'puppetlabs-python', '4.0.0'
mod 'puppetlabs-apache', '5.0.0'
mod 'puppetlabs-mysql', '10.0.0'
mod 'puppetlabs-firewall', '2.0.0'
mod 'puppetlabs-logrotate', '3.0.0'
mod 'puppetlabs-cron_core', '1.0.0'
"""
            
            file_path = os.path.join(self.working_directory, self.puppetfile)
            with open(file_path, 'w') as f:
                f.write(puppetfile_content)
            
            print(f"✓ Puppetfile created")
            return True
        except Exception as e:
            print(f"✗ Error creating Puppetfile: {e}")
            return False
    
    def create_hiera_config(self) -> bool:
        """Create Hiera configuration file."""
        try:
            print("Creating Hiera configuration...")
            
            hiera_config_content = """---
version: 5

defaults:
  datadir: data
  data_hash: yaml_data

hierarchy:
  - name: "Common"
    path: "common.yaml"
"""
            
            file_path = os.path.join(self.working_directory, self.hiera_config)
            with open(file_path, 'w') as f:
                f.write(hiera_config_content)
            
            print(f"✓ Hiera configuration created")
            return True
        except Exception as e:
            print(f"✗ Error creating Hiera configuration: {e}")
            return False
    
    def create_hiera_data(self) -> bool:
        """Create Hiera data files."""
        try:
            print("Creating Hiera data files...")
            
            os.makedirs(os.path.join(self.working_directory, self.hiera_data), exist_ok=True)
            
            common_data_content = """---
# FaceNet Common Configuration

facenet::project_name: 'facenet'
facenet::environment: 'dev'
facenet::version: '1.0.0'
facenet::user: 'facenet'
facenet::group: 'facenet'
facenet::home: '/opt/facenet'
facenet::log_dir: '/var/log/facenet'
facenet::data_dir: '/opt/facenet/data'
facenet::models_dir: '/opt/facenet/models'
facenet::app_port: 8080
facenet::app_host: '0.0.0.0'
facenet::app_debug: true
facenet::app_log_level: 'INFO'
facenet::face_crop_size: 160
facenet::face_crop_margin: 32
facenet::recognition_threshold: 1.0
facenet::normalize_embeddings: true
facenet::recognition_method: 'euclidean'
facenet::db_host: 'localhost'
facenet::db_port: 3306
facenet::db_name: 'facenet_db'
facenet::db_user: 'admin'
facenet::db_password: 'adminpassword123'
facenet::python_version: '3.8'
facenet::pip_requirements:
  - 'tensorflow==1.7'
  - 'numpy'
  - 'scipy'
  - 'scikit-learn'
  - 'opencv-python'
  - 'pillow'
  - 'h5py'
  - 'matplotlib'
  - 'requests'
  - 'psutil'
  - 'mysql-connector-python'
facenet::apache_document_root: '/var/www/html'
facenet::apache_server_name: 'facenet.local'
facenet::apache_ssl_enabled: false
facenet::service_name: 'facenet'
facenet::service_description: 'FaceNet Service'
facenet::service_user: 'apache'
facenet::service_group: 'apache'
facenet::service_working_directory: '/var/www/html/facenet'
facenet::service_executable: '/usr/bin/python3'
facenet::service_script: 'facenet_service.py'
facenet::service_restart: 'always'
facenet::service_restart_sec: 10
facenet::log_file: 'facenet.log'
facenet::log_max_size: '10MB'
facenet::log_backup_count: 5
facenet::log_level: 'INFO'
facenet::monitoring_enabled: true
facenet::health_check_path: '/health'
facenet::health_check_interval: 30
facenet::metrics_enabled: true
facenet::metrics_port: 9090
facenet::firewall_enabled: true
facenet::allowed_ports: [22, 80, 443, 8080, 9090]
facenet::backup_enabled: true
facenet::backup_directory: '/opt/backups'
facenet::backup_retention_days: 7
facenet::backup_schedule: '0 2 * * *'
"""
            
            file_path = os.path.join(self.working_directory, self.hiera_data, 'common.yaml')
            with open(file_path, 'w') as f:
                f.write(common_data_content)
            
            print(f"✓ Hiera data files created")
            return True
        except Exception as e:
            print(f"✗ Error creating Hiera data files: {e}")
            return False
    
    def create_puppet_files(self) -> bool:
        """Create all Puppet files."""
        try:
            print("Creating Puppet files...")
            
            if not self.create_puppet_directory():
                return False
            
            if not self.create_module_structure():
                return False
            
            if not self.create_metadata_file():
                return False
            
            if not self.create_init_manifest():
                return False
            
            if not self.create_puppetfile():
                return False
            
            if not self.create_hiera_config():
                return False
            
            if not self.create_hiera_data():
                return False
            
            print("✓ All Puppet files created")
            return True
        except Exception as e:
            print(f"✗ Error creating Puppet files: {e}")
            return False
    
    def r10k_deploy(self) -> bool:
        """Deploy modules using r10k."""
        try:
            print("Deploying modules using r10k...")
            
            result = subprocess.run([
                'r10k', 'deploy', 'environment', '-p'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error deploying modules: {result.stderr}")
                return False
            
            print("✓ Modules deployed")
            return True
        except Exception as e:
            print(f"✗ Error deploying modules: {e}")
            return False
    
    def puppet_apply(self, manifest: str) -> bool:
        """Apply Puppet manifest."""
        try:
            print(f"Applying Puppet manifest: {manifest}")
            
            result = subprocess.run([
                'puppet', 'apply', '--modulepath', 'modules', manifest
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error applying manifest: {result.stderr}")
                return False
            
            print("✓ Manifest applied")
            return True
        except Exception as e:
            print(f"✗ Error applying manifest: {e}")
            return False
    
    def puppet_agent(self) -> bool:
        """Run Puppet agent."""
        try:
            print("Running Puppet agent...")
            
            result = subprocess.run([
                'puppet', 'agent', '--test'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error running agent: {result.stderr}")
                return False
            
            print("✓ Agent run completed")
            return True
        except Exception as e:
            print(f"✗ Error running agent: {e}")
            return False
    
    def deploy_with_puppet(self) -> bool:
        """Deploy FaceNet with Puppet."""
        try:
            print("Deploying FaceNet with Puppet...")
            
            # Create Puppet files
            if not self.create_puppet_files():
                return False
            
            # Deploy modules
            if not self.r10k_deploy():
                return False
            
            # Apply manifest
            manifest_path = os.path.join(self.working_directory, 'manifests', 'site.pp')
            if not self.puppet_apply(manifest_path):
                return False
            
            print("✓ FaceNet deployed with Puppet successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying with Puppet: {e}")
            return False
    
    def get_puppet_info(self) -> Dict:
        """Get comprehensive Puppet information."""
        try:
            info = {
                'working_directory': self.working_directory,
                'module_name': self.module_name,
                'manifest_name': self.manifest_name,
                'manifests_directory': self.manifests_directory,
                'templates_directory': self.templates_directory,
                'files_directory': self.files_directory,
                'facts_directory': self.facts_directory,
                'functions_directory': self.functions_directory,
                'types_directory': self.types_directory,
                'providers_directory': self.providers_directory,
                'metadata_file': self.metadata_file,
                'puppetfile': self.puppetfile,
                'hiera_config': self.hiera_config,
                'hiera_data': self.hiera_data,
                'environment': self.environment
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Puppet Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_puppet.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet with Puppet")
        print("  deploy-modules - Deploy modules using r10k")
        print("  apply       - Apply Puppet manifest")
        print("  agent       - Run Puppet agent")
        print("  info        - Show Puppet information")
        return
    
    command = sys.argv[1]
    puppet_manager = FaceNetPuppet()
    
    if command == 'deploy':
        puppet_manager.deploy_with_puppet()
    elif command == 'deploy-modules':
        puppet_manager.r10k_deploy()
    elif command == 'apply':
        if len(sys.argv) < 3:
            print("Usage: python facenet_puppet.py apply <manifest_file>")
            return
        manifest_file = sys.argv[2]
        puppet_manager.puppet_apply(manifest_file)
    elif command == 'agent':
        puppet_manager.puppet_agent()
    elif command == 'info':
        info = puppet_manager.get_puppet_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
