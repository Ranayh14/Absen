#!/usr/bin/env python3
"""
FaceNet Salt Support

This script provides Salt support for FaceNet service.
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

class FaceNetSalt:
    """Salt manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Salt manager."""
        self.working_directory = 'salt'
        self.formula_name = 'facenet'
        self.init_sls = 'init.sls'
        self.states_directory = 'states'
        self.templates_directory = 'templates'
        self.files_directory = 'files'
        self.pillars_directory = 'pillars'
        self.grains_directory = 'grains'
        self.reactors_directory = 'reactors'
        self.returners_directory = 'returners'
        self.metadata_file = 'metadata.yml'
        self.top_file = 'top.sls'
        self.pillar_file = 'pillar.sls'
        self.grains_file = 'grains.yml'
        self.reactor_file = 'reactor.sls'
        self.returner_file = 'returner.sls'
        self.master_config = 'master'
        self.minion_config = 'minion'
        self.environment = 'base'
    
    def create_salt_directory(self) -> bool:
        """Create Salt working directory."""
        try:
            print("Creating Salt directory...")
            
            os.makedirs(self.working_directory, exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, 'formulas'), exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, 'pillar'), exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, 'grains'), exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, 'reactor'), exist_ok=True)
            os.makedirs(os.path.join(self.working_directory, 'returner'), exist_ok=True)
            
            print(f"✓ Salt directory {self.working_directory} created")
            return True
        except Exception as e:
            print(f"✗ Error creating Salt directory: {e}")
            return False
    
    def create_formula_structure(self) -> bool:
        """Create formula directory structure."""
        try:
            print("Creating formula structure...")
            
            formula_path = os.path.join(self.working_directory, 'formulas', self.formula_name)
            
            # Create formula directories
            for subdir in [self.states_directory, self.templates_directory, self.files_directory, 
                          self.pillars_directory, self.grains_directory, self.reactors_directory, 
                          self.returners_directory, 'tests']:
                os.makedirs(os.path.join(formula_path, subdir), exist_ok=True)
            
            # Create templates subdirectories
            os.makedirs(os.path.join(formula_path, self.templates_directory, 'default'), exist_ok=True)
            
            # Create files subdirectories
            os.makedirs(os.path.join(formula_path, self.files_directory, 'default'), exist_ok=True)
            
            print(f"✓ Formula structure created at {formula_path}")
            return True
        except Exception as e:
            print(f"✗ Error creating formula structure: {e}")
            return False
    
    def create_metadata_file(self) -> bool:
        """Create formula metadata file."""
        try:
            print("Creating formula metadata file...")
            
            metadata_content = """---
name: facenet
full_name: FaceNet Formula
description: Installs and configures FaceNet service
version: 1.0.0
license: MIT
author: FaceNet Team
email: team@facenet.com
homepage: https://github.com/facenet/salt-formula
source: https://github.com/facenet/salt-formula
issues: https://github.com/facenet/salt-formula/issues
platforms:
  - name: RedHat
    versions:
      - 7
      - 8
  - name: CentOS
    versions:
      - 7
      - 8
  - name: Amazon
    versions:
      - 2
dependencies:
  - name: python
    version: 4.0.0
  - name: apache
    version: 2.0.0
  - name: mysql
    version: 2.0.0
  - name: firewall
    version: 1.0.0
tags:
  - facenet
  - face-recognition
  - machine-learning
"""
            
            formula_path = os.path.join(self.working_directory, 'formulas', self.formula_name)
            file_path = os.path.join(formula_path, self.metadata_file)
            with open(file_path, 'w') as f:
                f.write(metadata_content)
            
            print(f"✓ Metadata file {self.metadata_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating metadata file: {e}")
            return False
    
    def create_init_sls(self) -> bool:
        """Create formula init.sls file."""
        try:
            print("Creating formula init.sls file...")
            
            init_sls_content = """# FaceNet Salt Formula

{%- set facenet = salt['pillar.get']('facenet', {}) %}
{%- set project_name = facenet.get('project_name', 'facenet') %}
{%- set environment = facenet.get('environment', 'dev') %}
{%- set version = facenet.get('version', '1.0.0') %}
{%- set user = facenet.get('user', 'facenet') %}
{%- set group = facenet.get('group', 'facenet') %}
{%- set home = facenet.get('home', '/opt/facenet') %}
{%- set log_dir = facenet.get('log_dir', '/var/log/facenet') %}
{%- set data_dir = facenet.get('data_dir', '/opt/facenet/data') %}
{%- set models_dir = facenet.get('models_dir', '/opt/facenet/models') %}
{%- set app_port = facenet.get('app_port', 8080) %}
{%- set app_host = facenet.get('app_host', '0.0.0.0') %}
{%- set app_debug = facenet.get('app_debug', true) %}
{%- set app_log_level = facenet.get('app_log_level', 'INFO') %}
{%- set face_crop_size = facenet.get('face_crop_size', 160) %}
{%- set face_crop_margin = facenet.get('face_crop_margin', 32) %}
{%- set recognition_threshold = facenet.get('recognition_threshold', 1.0) %}
{%- set normalize_embeddings = facenet.get('normalize_embeddings', true) %}
{%- set recognition_method = facenet.get('recognition_method', 'euclidean') %}
{%- set db_host = facenet.get('db_host', 'localhost') %}
{%- set db_port = facenet.get('db_port', 3306) %}
{%- set db_name = facenet.get('db_name', 'facenet_db') %}
{%- set db_user = facenet.get('db_user', 'admin') %}
{%- set db_password = facenet.get('db_password', 'adminpassword123') %}
{%- set python_version = facenet.get('python_version', '3.8') %}
{%- set pip_requirements = facenet.get('pip_requirements', [
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
]) %}
{%- set apache_document_root = facenet.get('apache_document_root', '/var/www/html') %}
{%- set apache_server_name = facenet.get('apache_server_name', 'facenet.local') %}
{%- set apache_ssl_enabled = facenet.get('apache_ssl_enabled', false) %}
{%- set service_name = facenet.get('service_name', 'facenet') %}
{%- set service_description = facenet.get('service_description', 'FaceNet Service') %}
{%- set service_user = facenet.get('service_user', 'apache') %}
{%- set service_group = facenet.get('service_group', 'apache') %}
{%- set service_working_directory = facenet.get('service_working_directory', '/var/www/html/facenet') %}
{%- set service_executable = facenet.get('service_executable', '/usr/bin/python3') %}
{%- set service_script = facenet.get('service_script', 'facenet_service.py') %}
{%- set service_restart = facenet.get('service_restart', 'always') %}
{%- set service_restart_sec = facenet.get('service_restart_sec', 10) %}
{%- set log_file = facenet.get('log_file', 'facenet.log') %}
{%- set log_max_size = facenet.get('log_max_size', '10MB') %}
{%- set log_backup_count = facenet.get('log_backup_count', 5) %}
{%- set log_level = facenet.get('log_level', 'INFO') %}
{%- set monitoring_enabled = facenet.get('monitoring_enabled', true) %}
{%- set health_check_path = facenet.get('health_check_path', '/health') %}
{%- set health_check_interval = facenet.get('health_check_interval', 30) %}
{%- set metrics_enabled = facenet.get('metrics_enabled', true) %}
{%- set metrics_port = facenet.get('metrics_port', 9090) %}
{%- set firewall_enabled = facenet.get('firewall_enabled', true) %}
{%- set allowed_ports = facenet.get('allowed_ports', [22, 80, 443, 8080, 9090]) %}
{%- set backup_enabled = facenet.get('backup_enabled', true) %}
{%- set backup_directory = facenet.get('backup_directory', '/opt/backups') %}
{%- set backup_retention_days = facenet.get('backup_retention_days', 7) %}
{%- set backup_schedule = facenet.get('backup_schedule', '0 2 * * *') %}

# Update system packages
update_system_packages:
  pkg.installed:
    - names:
      - yum-utils
    - require_in:
      - cmd: yum_update

yum_update:
  cmd.run:
    - name: yum update -y
    - require:
      - pkg: update_system_packages

# Install system dependencies
install_system_dependencies:
  pkg.installed:
    - names:
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
    - require:
      - cmd: yum_update

# Install Python dependencies
{% for package in pip_requirements %}
install_python_package_{{ package|replace('==', '_')|replace('-', '_') }}:
  pip.installed:
    - name: {{ package }}
    - require:
      - pkg: install_system_dependencies
{% endfor %}

# Create system user
create_facenet_user:
  user.present:
    - name: {{ user }}
    - gid: {{ group }}
    - home: {{ home }}
    - shell: /bin/bash
    - system: True
    - createhome: True

create_facenet_group:
  group.present:
    - name: {{ group }}
    - members:
      - {{ user }}

# Create application directories
{% for dir in [home, log_dir, data_dir, models_dir] %}
create_directory_{{ dir|replace('/', '_')|replace('-', '_') }}:
  file.directory:
    - name: {{ dir }}
    - user: {{ user }}
    - group: {{ group }}
    - mode: 755
    - makedirs: True
{% endfor %}

# Download FaceNet models
download_facenet_keras_model:
  file.managed:
    - name: {{ models_dir }}/facenet_keras.h5
    - source: https://github.com/nyoki-mtl/keras-facenet/releases/download/v0.0.1/facenet_keras.h5
    - user: {{ user }}
    - group: {{ group }}
    - mode: 644
    - require:
      - file: create_directory_{{ models_dir|replace('/', '_')|replace('-', '_') }}

download_mtcnn_weights:
  file.managed:
    - name: {{ models_dir }}/mtcnn_weights.npz
    - source: https://github.com/ipazc/mtcnn/releases/download/v1.0.0/mtcnn_weights.npz
    - user: {{ user }}
    - group: {{ group }}
    - mode: 644
    - require:
      - file: create_directory_{{ models_dir|replace('/', '_')|replace('-', '_') }}

# Configure Apache
configure_apache:
  file.managed:
    - name: /etc/httpd/conf/httpd.conf
    - source: salt://{{ formula_name }}/templates/httpd.conf.jinja
    - template: jinja
    - user: root
    - group: root
    - mode: 644
    - context:
        document_root: {{ apache_document_root }}
        server_name: {{ apache_server_name }}
    - require:
      - pkg: install_system_dependencies
    - watch_in:
      - service: start_apache

# Configure PHP
configure_php:
  file.managed:
    - name: /etc/php.ini
    - source: salt://{{ formula_name }}/templates/php.ini.jinja
    - template: jinja
    - user: root
    - group: root
    - mode: 644
    - require:
      - pkg: install_system_dependencies
    - watch_in:
      - service: start_apache

# Start and enable Apache
start_apache:
  service.running:
    - name: httpd
    - enable: True
    - require:
      - pkg: install_system_dependencies

# Create FaceNet configuration
create_facenet_config:
  file.managed:
    - name: {{ home }}/facenet_config.py
    - source: salt://{{ formula_name }}/templates/facenet_config.py.jinja
    - template: jinja
    - user: {{ user }}
    - group: {{ group }}
    - mode: 644
    - context:
        face_crop_size: {{ face_crop_size }}
        face_crop_margin: {{ face_crop_margin }}
        recognition_threshold: {{ recognition_threshold }}
        normalize_embeddings: {{ normalize_embeddings }}
        recognition_method: {{ recognition_method }}
    - require:
      - file: create_directory_{{ home|replace('/', '_')|replace('-', '_') }}

# Create FaceNet database configuration
create_facenet_database_config:
  file.managed:
    - name: {{ home }}/facenet_database.py
    - source: salt://{{ formula_name }}/templates/facenet_database.py.jinja
    - template: jinja
    - user: {{ user }}
    - group: {{ group }}
    - mode: 644
    - context:
        db_host: {{ db_host }}
        db_port: {{ db_port }}
        db_name: {{ db_name }}
        db_user: {{ db_user }}
        db_password: {{ db_password }}
    - require:
      - file: create_directory_{{ home|replace('/', '_')|replace('-', '_') }}

# Create FaceNet service script
create_facenet_service:
  file.managed:
    - name: {{ home }}/facenet_service.py
    - source: salt://{{ formula_name }}/templates/facenet_service.py.jinja
    - template: jinja
    - user: {{ user }}
    - group: {{ group }}
    - mode: 755
    - require:
      - file: create_directory_{{ home|replace('/', '_')|replace('-', '_') }}

# Create FaceNet CLI script
create_facenet_cli:
  file.managed:
    - name: {{ home }}/facenet_cli.py
    - source: salt://{{ formula_name }}/templates/facenet_cli.py.jinja
    - template: jinja
    - user: {{ user }}
    - group: {{ group }}
    - mode: 755
    - require:
      - file: create_directory_{{ home|replace('/', '_')|replace('-', '_') }}

# Create FaceNet API script
create_facenet_api:
  file.managed:
    - name: {{ home }}/facenet_api.php
    - source: salt://{{ formula_name }}/templates/facenet_api.php.jinja
    - template: jinja
    - user: {{ user }}
    - group: {{ group }}
    - mode: 644
    - require:
      - file: create_directory_{{ home|replace('/', '_')|replace('-', '_') }}

# Create main application file
create_main_application:
  file.managed:
    - name: {{ home }}/index.php
    - source: salt://{{ formula_name }}/templates/index.php.jinja
    - template: jinja
    - user: {{ user }}
    - group: {{ group }}
    - mode: 644
    - require:
      - file: create_directory_{{ home|replace('/', '_')|replace('-', '_') }}

# Create systemd service file
create_systemd_service:
  file.managed:
    - name: /etc/systemd/system/{{ service_name }}.service
    - source: salt://{{ formula_name }}/templates/facenet.service.jinja
    - template: jinja
    - user: root
    - group: root
    - mode: 644
    - context:
        service_name: {{ service_name }}
        service_description: {{ service_description }}
        service_user: {{ service_user }}
        service_group: {{ service_group }}
        service_working_directory: {{ service_working_directory }}
        service_executable: {{ service_executable }}
        service_script: {{ service_script }}
        service_restart: {{ service_restart }}
        service_restart_sec: {{ service_restart_sec }}
    - watch_in:
      - service: start_facenet_service

# Start and enable FaceNet service
start_facenet_service:
  service.running:
    - name: {{ service_name }}
    - enable: True
    - require:
      - file: create_systemd_service

# Configure firewall
{% if firewall_enabled %}
{% for port in allowed_ports %}
configure_firewall_port_{{ port }}:
  firewalld.present:
    - name: {{ port }}
    - port: {{ port }}
    - protocol: tcp
    - action: accept
{% endfor %}
{% endif %}

# Create log rotation configuration
create_log_rotation:
  file.managed:
    - name: /etc/logrotate.d/{{ service_name }}
    - source: salt://{{ formula_name }}/templates/facenet.logrotate.jinja
    - template: jinja
    - user: root
    - group: root
    - mode: 644
    - context:
        log_file: {{ log_dir }}/{{ log_file }}
        max_size: {{ log_max_size }}
        backup_count: {{ log_backup_count }}

# Create monitoring script
{% if monitoring_enabled %}
create_monitoring_script:
  file.managed:
    - name: /opt/scripts/monitor.sh
    - source: salt://{{ formula_name }}/templates/monitor.sh.jinja
    - template: jinja
    - user: root
    - group: root
    - mode: 755
    - context:
        service_name: {{ service_name }}
        health_check_path: {{ health_check_path }}
        health_check_interval: {{ health_check_interval }}

setup_monitoring_cron:
  cron.present:
    - name: FaceNet Monitoring
    - minute: '*/5'
    - command: /opt/scripts/monitor.sh
    - user: root
{% endif %}

# Create backup script
{% if backup_enabled %}
create_backup_script:
  file.managed:
    - name: /opt/scripts/backup.sh
    - source: salt://{{ formula_name }}/templates/backup.sh.jinja
    - template: jinja
    - user: root
    - group: root
    - mode: 755
    - context:
        backup_directory: {{ backup_directory }}
        retention_days: {{ backup_retention_days }}

setup_backup_cron:
  cron.present:
    - name: FaceNet Backup
    - minute: '0'
    - hour: '2'
    - command: /opt/scripts/backup.sh
    - user: root
{% endif %}
"""
            
            formula_path = os.path.join(self.working_directory, 'formulas', self.formula_name)
            file_path = os.path.join(formula_path, self.states_directory, self.init_sls)
            with open(file_path, 'w') as f:
                f.write(init_sls_content)
            
            print(f"✓ Init.sls file {self.init_sls} created")
            return True
        except Exception as e:
            print(f"✗ Error creating init.sls file: {e}")
            return False
    
    def create_top_file(self) -> bool:
        """Create top.sls file."""
        try:
            print("Creating top.sls file...")
            
            top_content = """# FaceNet Salt Top File

base:
  '*':
    - {{ formula_name }}
"""
            
            file_path = os.path.join(self.working_directory, self.top_file)
            with open(file_path, 'w') as f:
                f.write(top_content)
            
            print(f"✓ Top file {self.top_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating top.sls file: {e}")
            return False
    
    def create_pillar_file(self) -> bool:
        """Create pillar.sls file."""
        try:
            print("Creating pillar.sls file...")
            
            pillar_content = """# FaceNet Salt Pillar

facenet:
  project_name: 'facenet'
  environment: 'dev'
  version: '1.0.0'
  user: 'facenet'
  group: 'facenet'
  home: '/opt/facenet'
  log_dir: '/var/log/facenet'
  data_dir: '/opt/facenet/data'
  models_dir: '/opt/facenet/models'
  app_port: 8080
  app_host: '0.0.0.0'
  app_debug: true
  app_log_level: 'INFO'
  face_crop_size: 160
  face_crop_margin: 32
  recognition_threshold: 1.0
  normalize_embeddings: true
  recognition_method: 'euclidean'
  db_host: 'localhost'
  db_port: 3306
  db_name: 'facenet_db'
  db_user: 'admin'
  db_password: 'adminpassword123'
  python_version: '3.8'
  pip_requirements:
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
  apache_document_root: '/var/www/html'
  apache_server_name: 'facenet.local'
  apache_ssl_enabled: false
  service_name: 'facenet'
  service_description: 'FaceNet Service'
  service_user: 'apache'
  service_group: 'apache'
  service_working_directory: '/var/www/html/facenet'
  service_executable: '/usr/bin/python3'
  service_script: 'facenet_service.py'
  service_restart: 'always'
  service_restart_sec: 10
  log_file: 'facenet.log'
  log_max_size: '10MB'
  log_backup_count: 5
  log_level: 'INFO'
  monitoring_enabled: true
  health_check_path: '/health'
  health_check_interval: 30
  metrics_enabled: true
  metrics_port: 9090
  firewall_enabled: true
  allowed_ports: [22, 80, 443, 8080, 9090]
  backup_enabled: true
  backup_directory: '/opt/backups'
  backup_retention_days: 7
  backup_schedule: '0 2 * * *'
"""
            
            file_path = os.path.join(self.working_directory, 'pillar', self.pillar_file)
            with open(file_path, 'w') as f:
                f.write(pillar_content)
            
            print(f"✓ Pillar file {self.pillar_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating pillar.sls file: {e}")
            return False
    
    def create_grains_file(self) -> bool:
        """Create grains.yml file."""
        try:
            print("Creating grains.yml file...")
            
            grains_content = """# FaceNet Salt Grains

facenet:
  project_name: 'facenet'
  environment: 'dev'
  version: '1.0.0'
  user: 'facenet'
  group: 'facenet'
  home: '/opt/facenet'
  log_dir: '/var/log/facenet'
  data_dir: '/opt/facenet/data'
  models_dir: '/opt/facenet/models'
  app_port: 8080
  app_host: '0.0.0.0'
  app_debug: true
  app_log_level: 'INFO'
  face_crop_size: 160
  face_crop_margin: 32
  recognition_threshold: 1.0
  normalize_embeddings: true
  recognition_method: 'euclidean'
  db_host: 'localhost'
  db_port: 3306
  db_name: 'facenet_db'
  db_user: 'admin'
  db_password: 'adminpassword123'
  python_version: '3.8'
  pip_requirements:
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
  apache_document_root: '/var/www/html'
  apache_server_name: 'facenet.local'
  apache_ssl_enabled: false
  service_name: 'facenet'
  service_description: 'FaceNet Service'
  service_user: 'apache'
  service_group: 'apache'
  service_working_directory: '/var/www/html/facenet'
  service_executable: '/usr/bin/python3'
  service_script: 'facenet_service.py'
  service_restart: 'always'
  service_restart_sec: 10
  log_file: 'facenet.log'
  log_max_size: '10MB'
  log_backup_count: 5
  log_level: 'INFO'
  monitoring_enabled: true
  health_check_path: '/health'
  health_check_interval: 30
  metrics_enabled: true
  metrics_port: 9090
  firewall_enabled: true
  allowed_ports: [22, 80, 443, 8080, 9090]
  backup_enabled: true
  backup_directory: '/opt/backups'
  backup_retention_days: 7
  backup_schedule: '0 2 * * *'
"""
            
            file_path = os.path.join(self.working_directory, 'grains', self.grains_file)
            with open(file_path, 'w') as f:
                f.write(grains_content)
            
            print(f"✓ Grains file {self.grains_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating grains.yml file: {e}")
            return False
    
    def create_reactor_file(self) -> bool:
        """Create reactor.sls file."""
        try:
            print("Creating reactor.sls file...")
            
            reactor_content = """# FaceNet Salt Reactor

{%- if data['fun'] == 'state.highstate' %}
{%- if data['retcode'] == 0 %}
{%- set message = 'FaceNet state applied successfully on ' + data['id'] %}
{%- else %}
{%- set message = 'FaceNet state failed on ' + data['id'] + ' with return code ' + data['retcode']|string %}
{%- endif %}
notify_facenet_state:
  local.cmd:
    - tgt: 'salt-master'
    - fun: 'event.send'
    - arg:
      - 'facenet/state/result'
      - message: {{ message }}
      - minion: {{ data['id'] }}
      - retcode: {{ data['retcode'] }}
      - timestamp: {{ data['_stamp'] }}
{%- endif %}
"""
            
            file_path = os.path.join(self.working_directory, 'reactor', self.reactor_file)
            with open(file_path, 'w') as f:
                f.write(reactor_content)
            
            print(f"✓ Reactor file {self.reactor_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating reactor.sls file: {e}")
            return False
    
    def create_returner_file(self) -> bool:
        """Create returner.sls file."""
        try:
            print("Creating returner.sls file...")
            
            returner_content = """# FaceNet Salt Returner

import json
import logging
import os
from datetime import datetime

log = logging.getLogger(__name__)

def __virtual__():
    return 'facenet'

def returner(ret):
    """
    Return FaceNet state results to a file.
    """
    try:
        # Create returner directory if it doesn't exist
        returner_dir = '/var/log/salt/returners'
        os.makedirs(returner_dir, exist_ok=True)
        
        # Create returner file
        returner_file = os.path.join(returner_dir, 'facenet_returns.log')
        
        # Prepare return data
        return_data = {
            'timestamp': datetime.now().isoformat(),
            'minion': ret.get('id'),
            'fun': ret.get('fun'),
            'jid': ret.get('jid'),
            'retcode': ret.get('retcode'),
            'success': ret.get('retcode') == 0,
            'changes': ret.get('changes', {}),
            'comment': ret.get('comment', ''),
            'result': ret.get('result', {})
        }
        
        # Write to file
        with open(returner_file, 'a') as f:
            f.write(json.dumps(return_data) + '\\n')
        
        log.info(f"FaceNet returner: {return_data}")
        
    except Exception as e:
        log.error(f"FaceNet returner error: {e}")

def event_return(events):
    """
    Return FaceNet events to a file.
    """
    try:
        # Create returner directory if it doesn't exist
        returner_dir = '/var/log/salt/returners'
        os.makedirs(returner_dir, exist_ok=True)
        
        # Create event file
        event_file = os.path.join(returner_dir, 'facenet_events.log')
        
        # Write events to file
        with open(event_file, 'a') as f:
            for event in events:
                event_data = {
                    'timestamp': datetime.now().isoformat(),
                    'tag': event.get('tag'),
                    'data': event.get('data', {})
                }
                f.write(json.dumps(event_data) + '\\n')
        
        log.info(f"FaceNet event returner: {len(events)} events")
        
    except Exception as e:
        log.error(f"FaceNet event returner error: {e}")
"""
            
            file_path = os.path.join(self.working_directory, 'returner', self.returner_file)
            with open(file_path, 'w') as f:
                f.write(returner_content)
            
            print(f"✓ Returner file {self.returner_file} created")
            return True
        except Exception as e:
            print(f"✗ Error creating returner.sls file: {e}")
            return False
    
    def create_salt_files(self) -> bool:
        """Create all Salt files."""
        try:
            print("Creating Salt files...")
            
            if not self.create_salt_directory():
                return False
            
            if not self.create_formula_structure():
                return False
            
            if not self.create_metadata_file():
                return False
            
            if not self.create_init_sls():
                return False
            
            if not self.create_top_file():
                return False
            
            if not self.create_pillar_file():
                return False
            
            if not self.create_grains_file():
                return False
            
            if not self.create_reactor_file():
                return False
            
            if not self.create_returner_file():
                return False
            
            print("✓ All Salt files created")
            return True
        except Exception as e:
            print(f"✗ Error creating Salt files: {e}")
            return False
    
    def salt_state_apply(self, state: str) -> bool:
        """Apply Salt state."""
        try:
            print(f"Applying Salt state: {state}")
            
            result = subprocess.run([
                'salt-call', 'state.apply', state
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error applying state: {result.stderr}")
                return False
            
            print("✓ State applied")
            return True
        except Exception as e:
            print(f"✗ Error applying state: {e}")
            return False
    
    def salt_highstate(self) -> bool:
        """Apply Salt highstate."""
        try:
            print("Applying Salt highstate...")
            
            result = subprocess.run([
                'salt-call', 'state.highstate'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error applying highstate: {result.stderr}")
                return False
            
            print("✓ Highstate applied")
            return True
        except Exception as e:
            print(f"✗ Error applying highstate: {e}")
            return False
    
    def salt_test_ping(self) -> bool:
        """Test Salt connectivity."""
        try:
            print("Testing Salt connectivity...")
            
            result = subprocess.run([
                'salt-call', 'test.ping'
            ], cwd=self.working_directory, capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error testing connectivity: {result.stderr}")
                return False
            
            print("✓ Connectivity test successful")
            return True
        except Exception as e:
            print(f"✗ Error testing connectivity: {e}")
            return False
    
    def deploy_with_salt(self) -> bool:
        """Deploy FaceNet with Salt."""
        try:
            print("Deploying FaceNet with Salt...")
            
            # Create Salt files
            if not self.create_salt_files():
                return False
            
            # Test connectivity
            if not self.salt_test_ping():
                return False
            
            # Apply highstate
            if not self.salt_highstate():
                return False
            
            print("✓ FaceNet deployed with Salt successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying with Salt: {e}")
            return False
    
    def get_salt_info(self) -> Dict:
        """Get comprehensive Salt information."""
        try:
            info = {
                'working_directory': self.working_directory,
                'formula_name': self.formula_name,
                'init_sls': self.init_sls,
                'states_directory': self.states_directory,
                'templates_directory': self.templates_directory,
                'files_directory': self.files_directory,
                'pillars_directory': self.pillars_directory,
                'grains_directory': self.grains_directory,
                'reactors_directory': self.reactors_directory,
                'returners_directory': self.returners_directory,
                'metadata_file': self.metadata_file,
                'top_file': self.top_file,
                'pillar_file': self.pillar_file,
                'grains_file': self.grains_file,
                'reactor_file': self.reactor_file,
                'returner_file': self.returner_file,
                'master_config': self.master_config,
                'minion_config': self.minion_config,
                'environment': self.environment
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Salt Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_salt.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet with Salt")
        print("  state       - Apply Salt state")
        print("  highstate   - Apply Salt highstate")
        print("  ping        - Test Salt connectivity")
        print("  info        - Show Salt information")
        return
    
    command = sys.argv[1]
    salt_manager = FaceNetSalt()
    
    if command == 'deploy':
        salt_manager.deploy_with_salt()
    elif command == 'state':
        if len(sys.argv) < 3:
            print("Usage: python facenet_salt.py state <state_name>")
            return
        state_name = sys.argv[2]
        salt_manager.salt_state_apply(state_name)
    elif command == 'highstate':
        salt_manager.salt_highstate()
    elif command == 'ping':
        salt_manager.salt_test_ping()
    elif command == 'info':
        info = salt_manager.get_salt_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
