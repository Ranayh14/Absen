#!/usr/bin/env python3
"""
FaceNet Ansible Support

This script provides Ansible support for FaceNet service.
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

class FaceNetAnsible:
    """Ansible manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Ansible manager."""
        self.working_directory = 'ansible'
        self.playbook_file = 'facenet.yml'
        self.inventory_file = 'inventory.ini'
        self.vars_file = 'vars.yml'
        self.roles_directory = 'roles'
        self.facenet_role = 'facenet'
    
    def create_ansible_directory(self) -> bool:
        """Create Ansible working directory."""
        try:
            print("Creating Ansible directory...")
            
            os.makedirs(self.working_directory, exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, self.roles_directory), exist_ok=True)
            
            print(f"✓ Ansible directory {self.working_directory} created")
            return True
        except Exception as e:
            print(f"✗ Error creating Ansible directory: {e}")
            return False
    
    def create_inventory_file(self) -> bool:
        """Create Ansible inventory file."""
        try:
            print("Creating Ansible inventory file...")
            
            inventory_content = """# FaceNet Ansible Inventory

[webservers]
web1 ansible_host=10.0.1.10 ansible_user=ec2-user ansible_ssh_private_key_file=~/.ssh/id_rsa
web2 ansible_host=10.0.1.11 ansible_user=ec2-user ansible_ssh_private_key_file=~/.ssh/id_rsa

[dbservers]
db1 ansible_host=10.0.10.10 ansible_user=ec2-user ansible_ssh_private_key_file=~/.ssh/id_rsa

[facenet:children]
webservers

[all:vars]
ansible_python_interpreter=/usr/bin/python3
ansible_ssh_common_args='-o StrictHostKeyChecking=no'
"""
            
            file_path = os.path.join(self.working_directory, self.inventory_file)
            with open(file_path, 'w') as f:
                f.write(inventory_content)
            
            print(f"✓ Inventory file {self.inventory_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating inventory file: {e}")
            return False
    
    def create_vars_file(self) -> bool:
        """Create Ansible variables file."""
        try:
            print("Creating Ansible variables file...")
            
            vars_content = """# FaceNet Ansible Variables

# Project settings
project_name: "facenet"
environment: "dev"
version: "1.0.0"

# Database settings
db_host: "{{ hostvars['db1']['ansible_host'] }}"
db_port: 3306
db_name: "facenet_db"
db_user: "admin"
db_password: "adminpassword123"

# Application settings
app_port: 8080
app_host: "0.0.0.0"
app_debug: true
app_log_level: "INFO"

# FaceNet settings
facenet_model_path: "/opt/facenet/models"
facenet_face_crop_size: 160
facenet_face_crop_margin: 32
facenet_recognition_threshold: 1.0
facenet_normalize_embeddings: true
facenet_recognition_method: "euclidean"

# System settings
system_user: "facenet"
system_group: "facenet"
system_home: "/opt/facenet"

# Python settings
python_version: "3.8"
pip_requirements:
  - tensorflow==1.7
  - numpy
  - scipy
  - scikit-learn
  - opencv-python
  - pillow
  - h5py
  - matplotlib
  - requests
  - psutil
  - mysql-connector-python

# Apache settings
apache_document_root: "/var/www/html"
apache_server_name: "facenet.local"
apache_ssl_enabled: false

# Service settings
service_name: "facenet"
service_description: "FaceNet Service"
service_user: "apache"
service_group: "apache"
service_working_directory: "/var/www/html/facenet"
service_executable: "/usr/bin/python3"
service_script: "facenet_service.py"
service_restart: "always"
service_restart_sec: 10

# Logging settings
log_directory: "/var/log/facenet"
log_file: "facenet.log"
log_max_size: "10MB"
log_backup_count: 5
log_level: "INFO"

# Monitoring settings
monitoring_enabled: true
health_check_path: "/health"
health_check_interval: 30
metrics_enabled: true
metrics_port: 9090

# Security settings
firewall_enabled: true
firewall_allowed_ports:
  - 22
  - 80
  - 443
  - 8080
  - 9090

# Backup settings
backup_enabled: true
backup_directory: "/opt/backups"
backup_retention_days: 7
backup_schedule: "0 2 * * *"

# Update settings
auto_update: false
update_schedule: "0 3 * * 0"
"""
            
            file_path = os.path.join(self.working_directory, self.vars_file)
            with open(file_path, 'w') as f:
                f.write(vars_content)
            
            print(f"✓ Variables file {self.vars_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating variables file: {e}")
            return False
    
    def create_playbook_file(self) -> bool:
        """Create Ansible playbook file."""
        try:
            print("Creating Ansible playbook file...")
            
            playbook_content = """---
# FaceNet Ansible Playbook

- name: Configure FaceNet Web Servers
  hosts: webservers
  become: yes
  vars_files:
    - vars.yml
  roles:
    - facenet

- name: Configure FaceNet Database Server
  hosts: dbservers
  become: yes
  vars_files:
    - vars.yml
  roles:
    - facenet-db

- name: Deploy FaceNet Application
  hosts: facenet
  become: yes
  vars_files:
    - vars.yml
  tasks:
    - name: Create application directory
      file:
        path: "{{ system_home }}"
        state: directory
        owner: "{{ system_user }}"
        group: "{{ system_group }}"
        mode: '0755'
    
    - name: Copy application files
      copy:
        src: "{{ item }}"
        dest: "{{ system_home }}/"
        owner: "{{ system_user }}"
        group: "{{ system_group }}"
        mode: '0644'
      loop:
        - facenet_service.py
        - facenet_cli.py
        - facenet_config.py
        - facenet_utils.py
        - facenet_database.py
        - facenet_api.php
        - index.php
    
    - name: Copy FaceNet models
      copy:
        src: "facenet-master/"
        dest: "{{ system_home }}/facenet-master/"
        owner: "{{ system_user }}"
        group: "{{ system_group }}"
        mode: '0755'
    
    - name: Create log directory
      file:
        path: "{{ log_directory }}"
        state: directory
        owner: "{{ system_user }}"
        group: "{{ system_group }}"
        mode: '0755'
    
    - name: Create systemd service file
      template:
        src: "facenet.service.j2"
        dest: "/etc/systemd/system/{{ service_name }}.service"
        owner: root
        group: root
        mode: '0644'
      notify: restart facenet
    
    - name: Enable and start FaceNet service
      systemd:
        name: "{{ service_name }}"
        enabled: yes
        state: started
        daemon_reload: yes
    
    - name: Configure Apache virtual host
      template:
        src: "facenet.conf.j2"
        dest: "/etc/httpd/conf.d/{{ project_name }}.conf"
        owner: root
        group: root
        mode: '0644'
      notify: restart apache
    
    - name: Configure firewall
      firewalld:
        port: "{{ item }}"
        permanent: yes
        state: enabled
        immediate: yes
      loop: "{{ firewall_allowed_ports }}"
      when: firewall_enabled
    
    - name: Create backup script
      template:
        src: "backup.sh.j2"
        dest: "/opt/scripts/backup.sh"
        owner: root
        group: root
        mode: '0755'
    
    - name: Setup backup cron job
      cron:
        name: "FaceNet Backup"
        job: "/opt/scripts/backup.sh"
        minute: "0"
        hour: "2"
        user: root
      when: backup_enabled
    
    - name: Create monitoring script
      template:
        src: "monitor.sh.j2"
        dest: "/opt/scripts/monitor.sh"
        owner: root
        group: root
        mode: '0755'
      when: monitoring_enabled
    
    - name: Setup monitoring cron job
      cron:
        name: "FaceNet Monitoring"
        job: "/opt/scripts/monitor.sh"
        minute: "*/5"
        user: root
      when: monitoring_enabled

  handlers:
    - name: restart facenet
      systemd:
        name: "{{ service_name }}"
        state: restarted
        daemon_reload: yes
    
    - name: restart apache
      systemd:
        name: httpd
        state: restarted
"""
            
            file_path = os.path.join(self.working_directory, self.playbook_file)
            with open(file_path, 'w') as f:
                f.write(playbook_content)
            
            print(f"✓ Playbook file {self.playbook_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating playbook file: {e}")
            return False
    
    def create_facenet_role(self) -> bool:
        """Create FaceNet Ansible role."""
        try:
            print("Creating FaceNet Ansible role...")
            
            role_path = os.path.join(self.working_directory, self.roles_directory, self.facenet_role)
            os.makedirs(role_path, exist_ok=True)
            
            # Create role directories
            for subdir in ['tasks', 'handlers', 'templates', 'files', 'vars', 'defaults', 'meta']:
                os.makedirs(os.path.join(role_path, subdir), exist_ok=True)
            
            # Create main tasks file
            main_tasks_content = """---
# FaceNet Role Main Tasks

- name: Update system packages
  yum:
    name: "*"
    state: latest
  when: ansible_os_family == "RedHat"

- name: Install system dependencies
  yum:
    name:
      - python3
      - python3-pip
      - gcc
      - gcc-c++
      - make
      - cmake
      - opencv-devel
      - gtk3-devel
      - mysql-devel
      - httpd
      - php
      - php-mysql
      - php-curl
    state: present
  when: ansible_os_family == "RedHat"

- name: Install Python dependencies
  pip:
    name: "{{ item }}"
    state: present
  loop: "{{ pip_requirements }}"

- name: Create system user
  user:
    name: "{{ system_user }}"
    group: "{{ system_group }}"
    home: "{{ system_home }}"
    shell: /bin/bash
    create_home: yes

- name: Create application directories
  file:
    path: "{{ item }}"
    state: directory
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0755'
  loop:
    - "{{ system_home }}"
    - "{{ system_home }}/models"
    - "{{ system_home }}/logs"
    - "{{ system_home }}/data"

- name: Download FaceNet models
  get_url:
    url: "{{ item.url }}"
    dest: "{{ system_home }}/models/{{ item.name }}"
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0644'
  loop:
    - { name: "facenet_keras.h5", url: "https://github.com/nyoki-mtl/keras-facenet/releases/download/v0.0.1/facenet_keras.h5" }
    - { name: "mtcnn_weights.npz", url: "https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.npz" }

- name: Configure Apache
  template:
    src: "httpd.conf.j2"
    dest: "/etc/httpd/conf/httpd.conf"
    owner: root
    group: root
    mode: '0644'
  notify: restart apache

- name: Start and enable Apache
  systemd:
    name: httpd
    state: started
    enabled: yes

- name: Configure PHP
  template:
    src: "php.ini.j2"
    dest: "/etc/php.ini"
    owner: root
    group: root
    mode: '0644'
  notify: restart apache

- name: Create FaceNet configuration
  template:
    src: "facenet_config.py.j2"
    dest: "{{ system_home }}/facenet_config.py"
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0644'

- name: Create FaceNet database configuration
  template:
    src: "facenet_database.py.j2"
    dest: "{{ system_home }}/facenet_database.py"
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0644'

- name: Create FaceNet service script
  template:
    src: "facenet_service.py.j2"
    dest: "{{ system_home }}/facenet_service.py"
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0755'

- name: Create FaceNet CLI script
  template:
    src: "facenet_cli.py.j2"
    dest: "{{ system_home }}/facenet_cli.py"
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0755'

- name: Create FaceNet API script
  template:
    src: "facenet_api.php.j2"
    dest: "{{ system_home }}/facenet_api.php"
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0644'

- name: Create main application file
  template:
    src: "index.php.j2"
    dest: "{{ system_home }}/index.php"
    owner: "{{ system_user }}"
    group: "{{ system_group }}"
    mode: '0644'

- name: Create systemd service file
  template:
    src: "facenet.service.j2"
    dest: "/etc/systemd/system/{{ service_name }}.service"
    owner: root
    group: root
    mode: '0644'
  notify: restart facenet

- name: Start and enable FaceNet service
  systemd:
    name: "{{ service_name }}"
    state: started
    enabled: yes
    daemon_reload: yes

- name: Create log rotation configuration
  template:
    src: "facenet.logrotate.j2"
    dest: "/etc/logrotate.d/{{ service_name }}"
    owner: root
    group: root
    mode: '0644'

- name: Create monitoring script
  template:
    src: "monitor.sh.j2"
    dest: "/opt/scripts/monitor.sh"
    owner: root
    group: root
    mode: '0755'
  when: monitoring_enabled

- name: Create backup script
  template:
    src: "backup.sh.j2"
    dest: "/opt/scripts/backup.sh"
    owner: root
    group: root
    mode: '0755'
  when: backup_enabled
"""
            
            main_tasks_path = os.path.join(role_path, 'tasks', 'main.yml')
            with open(main_tasks_path, 'w') as f:
                f.write(main_tasks_content)
            
            # Create handlers file
            handlers_content = """---
# FaceNet Role Handlers

- name: restart apache
  systemd:
    name: httpd
    state: restarted

- name: restart facenet
  systemd:
    name: "{{ service_name }}"
    state: restarted
    daemon_reload: yes
"""
            
            handlers_path = os.path.join(role_path, 'handlers', 'main.yml')
            with open(handlers_path, 'w') as f:
                f.write(handlers_content)
            
            # Create defaults file
            defaults_content = """---
# FaceNet Role Defaults

service_name: "facenet"
system_user: "facenet"
system_group: "facenet"
system_home: "/opt/facenet"
"""
            
            defaults_path = os.path.join(role_path, 'defaults', 'main.yml')
            with open(defaults_path, 'w') as f:
                f.write(defaults_content)
            
            # Create meta file
            meta_content = """---
galaxy_info:
  author: FaceNet Team
  description: FaceNet service role
  company: FaceNet
  license: MIT
  min_ansible_version: 2.9
  platforms:
    - name: EL
      versions:
        - 7
        - 8
  galaxy_tags:
    - facenet
    - face-recognition
    - machine-learning

dependencies: []
"""
            
            meta_path = os.path.join(role_path, 'meta', 'main.yml')
            with open(meta_path, 'w') as f:
                f.write(meta_content)
            
            print(f"✓ FaceNet role created at {role_path}")
            return True
        except Exception as e:
            print(f"✗ Error creating FaceNet role: {e}")
            return False
    
    def create_ansible_files(self) -> bool:
        """Create all Ansible files."""
        try:
            print("Creating Ansible files...")
            
            if not self.create_ansible_directory():
                return False
            
            if not self.create_inventory_file():
                return False
            
            if not self.create_vars_file():
                return False
            
            if not self.create_playbook_file():
                return False
            
            if not self.create_facenet_role():
                return False
            
            print("✓ All Ansible files created")
            return True
        except Exception as e:
            print(f"✗ Error creating Ansible files: {e}")
            return False
    
    def ansible_playbook(self, playbook: str, inventory: str, extra_vars: Dict = None) -> bool:
        """Run Ansible playbook."""
        try:
            print(f"Running Ansible playbook: {playbook}")
            
            cmd = ['ansible-playbook', '-i', inventory, playbook]
            
            if extra_vars:
                for key, value in extra_vars.items():
                    cmd.extend(['-e', f"{key}={value}"])
            
            result = subprocess.run(cmd, cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error running playbook: {result.stderr}")
                return False
            
            print("✓ Playbook executed successfully")
            return True
        except Exception as e:
            print(f"✗ Error running playbook: {e}")
            return False
    
    def ansible_ping(self, inventory: str) -> bool:
        """Test Ansible connectivity."""
        try:
            print("Testing Ansible connectivity...")
            
            result = subprocess.run([
                'ansible', 'all', '-i', inventory, '-m', 'ping'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error testing connectivity: {result.stderr}")
                return False
            
            print("✓ Connectivity test successful")
            return True
        except Exception as e:
            print(f"✗ Error testing connectivity: {e}")
            return False
    
    def deploy_with_ansible(self) -> bool:
        """Deploy FaceNet with Ansible."""
        try:
            print("Deploying FaceNet with Ansible...")
            
            # Create Ansible files
            if not self.create_ansible_files():
                return False
            
            # Test connectivity
            if not self.ansible_ping(self.inventory_file):
                return False
            
            # Run playbook
            if not self.ansible_playbook(self.playbook_file, self.inventory_file):
                return False
            
            print("✓ FaceNet deployed with Ansible successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying with Ansible: {e}")
            return False
    
    def get_ansible_info(self) -> Dict:
        """Get comprehensive Ansible information."""
        try:
            info = {
                'working_directory': self.working_directory,
                'playbook_file': self.playbook_file,
                'inventory_file': self.inventory_file,
                'vars_file': self.vars_file,
                'roles_directory': self.roles_directory,
                'facenet_role': self.facenet_role
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Ansible Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_ansible.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet with Ansible")
        print("  ping        - Test Ansible connectivity")
        print("  playbook    - Run Ansible playbook")
        print("  info        - Show Ansible information")
        return
    
    command = sys.argv[1]
    ansible_manager = FaceNetAnsible()
    
    if command == 'deploy':
        ansible_manager.deploy_with_ansible()
    elif command == 'ping':
        ansible_manager.ansible_ping(ansible_manager.inventory_file)
    elif command == 'playbook':
        if len(sys.argv) < 3:
            print("Usage: python facenet_ansible.py playbook <playbook_file>")
            return
        playbook_file = sys.argv[2]
        ansible_manager.ansible_playbook(playbook_file, ansible_manager.inventory_file)
    elif command == 'info':
        info = ansible_manager.get_ansible_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
