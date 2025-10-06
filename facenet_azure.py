#!/usr/bin/env python3
"""
FaceNet Azure Support

This script provides Azure support for FaceNet service.
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

class FaceNetAzure:
    """Azure manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Azure manager."""
        self.resource_group = 'facenet-rg'
        self.location = 'eastus'
        self.vm_name = 'facenet-vm'
        self.vm_size = 'Standard_B2s'
        self.storage_account = 'facenetstorage'
        self.container_name = 'facenet-models'
        self.sql_server = 'facenet-sql-server'
        self.sql_database = 'facenet-db'
    
    def create_resource_group(self) -> bool:
        """Create Azure resource group."""
        try:
            print("Creating Azure resource group...")
            
            result = subprocess.run([
                'az', 'group', 'create',
                '--name', self.resource_group,
                '--location', self.location
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating resource group: {result.stderr}")
                return False
            
            print(f"✓ Resource group {self.resource_group} created")
            return True
        except Exception as e:
            print(f"✗ Error creating resource group: {e}")
            return False
    
    def create_virtual_machine(self) -> bool:
        """Create Azure virtual machine."""
        try:
            print("Creating Azure virtual machine...")
            
            # Create VM
            result = subprocess.run([
                'az', 'vm', 'create',
                '--resource-group', self.resource_group,
                '--name', self.vm_name,
                '--image', 'UbuntuLTS',
                '--size', self.vm_size,
                '--admin-username', 'azureuser',
                '--generate-ssh-keys',
                '--public-ip-sku', 'Standard'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating VM: {result.stderr}")
                return False
            
            # Open ports
            ports = ['22', '80', '443', '8080', '3306']
            for port in ports:
                result = subprocess.run([
                    'az', 'vm', 'open-port',
                    '--resource-group', self.resource_group,
                    '--name', self.vm_name,
                    '--port', port
                ], capture_output=True, text=True)
                
                if result.returncode != 0:
                    print(f"⚠ Warning: Error opening port {port}: {result.stderr}")
            
            print(f"✓ Virtual machine {self.vm_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating VM: {e}")
            return False
    
    def create_storage_account(self) -> bool:
        """Create Azure storage account."""
        try:
            print("Creating Azure storage account...")
            
            result = subprocess.run([
                'az', 'storage', 'account', 'create',
                '--name', self.storage_account,
                '--resource-group', self.resource_group,
                '--location', self.location,
                '--sku', 'Standard_LRS'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating storage account: {result.stderr}")
                return False
            
            # Create container
            result = subprocess.run([
                'az', 'storage', 'container', 'create',
                '--name', self.container_name,
                '--account-name', self.storage_account
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating container: {result.stderr}")
                return False
            
            print(f"✓ Storage account {self.storage_account} created")
            return True
        except Exception as e:
            print(f"✗ Error creating storage account: {e}")
            return False
    
    def create_sql_database(self) -> bool:
        """Create Azure SQL database."""
        try:
            print("Creating Azure SQL database...")
            
            # Create SQL server
            result = subprocess.run([
                'az', 'sql', 'server', 'create',
                '--name', self.sql_server,
                '--resource-group', self.resource_group,
                '--location', self.location,
                '--admin-user', 'sqladmin',
                '--admin-password', 'SqlPassword123!'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating SQL server: {result.stderr}")
                return False
            
            # Create SQL database
            result = subprocess.run([
                'az', 'sql', 'db', 'create',
                '--resource-group', self.resource_group,
                '--server', self.sql_server,
                '--name', self.sql_database,
                '--service-objective', 'Basic'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating SQL database: {result.stderr}")
                return False
            
            print(f"✓ SQL database {self.sql_database} created")
            return True
        except Exception as e:
            print(f"✗ Error creating SQL database: {e}")
            return False
    
    def create_app_service(self) -> bool:
        """Create Azure App Service."""
        try:
            print("Creating Azure App Service...")
            
            # Create App Service plan
            result = subprocess.run([
                'az', 'appservice', 'plan', 'create',
                '--name', 'facenet-plan',
                '--resource-group', self.resource_group,
                '--location', self.location,
                '--sku', 'B1'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating App Service plan: {result.stderr}")
                return False
            
            # Create web app
            result = subprocess.run([
                'az', 'webapp', 'create',
                '--resource-group', self.resource_group,
                '--plan', 'facenet-plan',
                '--name', 'facenet-webapp',
                '--runtime', 'PYTHON|3.8'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating web app: {result.stderr}")
                return False
            
            print("✓ App Service created")
            return True
        except Exception as e:
            print(f"✗ Error creating App Service: {e}")
            return False
    
    def create_container_registry(self) -> bool:
        """Create Azure Container Registry."""
        try:
            print("Creating Azure Container Registry...")
            
            result = subprocess.run([
                'az', 'acr', 'create',
                '--resource-group', self.resource_group,
                '--name', 'facenetregistry',
                '--sku', 'Basic',
                '--admin-enabled', 'true'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating Container Registry: {result.stderr}")
                return False
            
            print("✓ Container Registry created")
            return True
        except Exception as e:
            print(f"✗ Error creating Container Registry: {e}")
            return False
    
    def create_kubernetes_service(self) -> bool:
        """Create Azure Kubernetes Service."""
        try:
            print("Creating Azure Kubernetes Service...")
            
            result = subprocess.run([
                'az', 'aks', 'create',
                '--resource-group', self.resource_group,
                '--name', 'facenet-aks',
                '--node-count', '2',
                '--node-vm-size', 'Standard_B2s',
                '--generate-ssh-keys',
                '--attach-acr', 'facenetregistry'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error creating AKS: {result.stderr}")
                return False
            
            print("✓ Azure Kubernetes Service created")
            return True
        except Exception as e:
            print(f"✗ Error creating AKS: {e}")
            return False
    
    def upload_models_to_storage(self) -> bool:
        """Upload FaceNet models to Azure Storage."""
        try:
            print("Uploading models to Azure Storage...")
            
            models_dir = 'facenet-master/models'
            if not os.path.exists(models_dir):
                print(f"⚠ Models directory {models_dir} not found")
                return False
            
            result = subprocess.run([
                'az', 'storage', 'blob', 'upload-batch',
                '--destination', self.container_name,
                '--source', models_dir,
                '--account-name', self.storage_account
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error uploading models: {result.stderr}")
                return False
            
            print("✓ Models uploaded to Azure Storage")
            return True
        except Exception as e:
            print(f"✗ Error uploading models: {e}")
            return False
    
    def create_arm_template(self) -> bool:
        """Create Azure Resource Manager template."""
        try:
            print("Creating ARM template...")
            
            template = self.get_arm_template()
            
            with open('facenet-template.json', 'w') as f:
                f.write(template)
            
            print("✓ ARM template created")
            return True
        except Exception as e:
            print(f"✗ Error creating ARM template: {e}")
            return False
    
    def get_arm_template(self) -> str:
        """Get Azure Resource Manager template."""
        return json.dumps({
            "$schema": "https://schema.management.azure.com/schemas/2019-04-01/deploymentTemplate.json#",
            "contentVersion": "1.0.0.0",
            "parameters": {
                "vmName": {
                    "type": "string",
                    "defaultValue": self.vm_name
                },
                "vmSize": {
                    "type": "string",
                    "defaultValue": self.vm_size
                },
                "storageAccountName": {
                    "type": "string",
                    "defaultValue": self.storage_account
                },
                "sqlServerName": {
                    "type": "string",
                    "defaultValue": self.sql_server
                },
                "sqlDatabaseName": {
                    "type": "string",
                    "defaultValue": self.sql_database
                }
            },
            "variables": {
                "location": self.location,
                "resourceGroup": self.resource_group
            },
            "resources": [
                {
                    "type": "Microsoft.Compute/virtualMachines",
                    "apiVersion": "2021-03-01",
                    "name": "[parameters('vmName')]",
                    "location": "[variables('location')]",
                    "properties": {
                        "hardwareProfile": {
                            "vmSize": "[parameters('vmSize')]"
                        },
                        "storageProfile": {
                            "imageReference": {
                                "publisher": "Canonical",
                                "offer": "UbuntuServer",
                                "sku": "18.04-LTS",
                                "version": "latest"
                            }
                        },
                        "osProfile": {
                            "computerName": "[parameters('vmName')]",
                            "adminUsername": "azureuser",
                            "linuxConfiguration": {
                                "disablePasswordAuthentication": True,
                                "ssh": {
                                    "publicKeys": [
                                        {
                                            "path": "/home/azureuser/.ssh/authorized_keys",
                                            "keyData": "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC..."
                                        }
                                    ]
                                }
                            }
                        },
                        "networkProfile": {
                            "networkInterfaces": [
                                {
                                    "id": "[resourceId('Microsoft.Network/networkInterfaces', concat(parameters('vmName'), '-nic'))]"
                                }
                            ]
                        }
                    }
                },
                {
                    "type": "Microsoft.Storage/storageAccounts",
                    "apiVersion": "2021-04-01",
                    "name": "[parameters('storageAccountName')]",
                    "location": "[variables('location')]",
                    "sku": {
                        "name": "Standard_LRS"
                    },
                    "kind": "StorageV2"
                },
                {
                    "type": "Microsoft.Sql/servers",
                    "apiVersion": "2020-11-01-preview",
                    "name": "[parameters('sqlServerName')]",
                    "location": "[variables('location')]",
                    "properties": {
                        "administratorLogin": "sqladmin",
                        "administratorLoginPassword": "SqlPassword123!"
                    }
                },
                {
                    "type": "Microsoft.Sql/servers/databases",
                    "apiVersion": "2020-11-01-preview",
                    "name": "[concat(parameters('sqlServerName'), '/', parameters('sqlDatabaseName'))]",
                    "dependsOn": [
                        "[resourceId('Microsoft.Sql/servers', parameters('sqlServerName'))]"
                    ],
                    "properties": {
                        "collation": "SQL_Latin1_General_CP1_CI_AS"
                    }
                }
            ],
            "outputs": {
                "vmId": {
                    "type": "string",
                    "value": "[resourceId('Microsoft.Compute/virtualMachines', parameters('vmName'))]"
                },
                "storageAccountId": {
                    "type": "string",
                    "value": "[resourceId('Microsoft.Storage/storageAccounts', parameters('storageAccountName'))]"
                },
                "sqlServerId": {
                    "type": "string",
                    "value": "[resourceId('Microsoft.Sql/servers', parameters('sqlServerName'))]"
                }
            }
        }, indent=2)
    
    def deploy_arm_template(self) -> bool:
        """Deploy ARM template."""
        try:
            print("Deploying ARM template...")
            
            result = subprocess.run([
                'az', 'deployment', 'group', 'create',
                '--resource-group', self.resource_group,
                '--template-file', 'facenet-template.json'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                print(f"✗ Error deploying ARM template: {result.stderr}")
                return False
            
            print("✓ ARM template deployed")
            return True
        except Exception as e:
            print(f"✗ Error deploying ARM template: {e}")
            return False
    
    def get_vm_status(self) -> Dict:
        """Get VM status."""
        try:
            result = subprocess.run([
                'az', 'vm', 'show',
                '--resource-group', self.resource_group,
                '--name', self.vm_name,
                '--query', '{name:name,provisioningState:provisioningState,powerState:instanceView.statuses[1].displayStatus}',
                '--output', 'json'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return json.loads(result.stdout)
        except Exception as e:
            return {'error': str(e)}
    
    def get_storage_status(self) -> Dict:
        """Get storage account status."""
        try:
            result = subprocess.run([
                'az', 'storage', 'account', 'show',
                '--name', self.storage_account,
                '--resource-group', self.resource_group,
                '--query', '{name:name,provisioningState:provisioningState,primaryLocation:primaryLocation}',
                '--output', 'json'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return json.loads(result.stdout)
        except Exception as e:
            return {'error': str(e)}
    
    def get_sql_status(self) -> Dict:
        """Get SQL database status."""
        try:
            result = subprocess.run([
                'az', 'sql', 'db', 'show',
                '--resource-group', self.resource_group,
                '--server', self.sql_server,
                '--name', self.sql_database,
                '--query', '{name:name,status:status,creationDate:creationDate}',
                '--output', 'json'
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            return json.loads(result.stdout)
        except Exception as e:
            return {'error': str(e)}
    
    def deploy_to_azure(self) -> bool:
        """Deploy FaceNet to Azure."""
        try:
            print("Deploying FaceNet to Azure...")
            
            # Create resource group
            if not self.create_resource_group():
                return False
            
            # Create storage account
            if not self.create_storage_account():
                return False
            
            # Upload models
            if not self.upload_models_to_storage():
                return False
            
            # Create VM
            if not self.create_virtual_machine():
                return False
            
            # Create SQL database
            if not self.create_sql_database():
                return False
            
            print("✓ FaceNet deployed to Azure successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying to Azure: {e}")
            return False
    
    def get_azure_info(self) -> Dict:
        """Get comprehensive Azure information."""
        try:
            info = {
                'resource_group': self.resource_group,
                'location': self.location,
                'vm_name': self.vm_name,
                'vm_size': self.vm_size,
                'storage_account': self.storage_account,
                'container_name': self.container_name,
                'sql_server': self.sql_server,
                'sql_database': self.sql_database,
                'vm_status': self.get_vm_status(),
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
    print("FaceNet Azure Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_azure.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet to Azure")
        print("  vm          - Create virtual machine")
        print("  storage     - Create storage account")
        print("  sql         - Create SQL database")
        print("  aks         - Create Azure Kubernetes Service")
        print("  status      - Show Azure resources status")
        print("  info        - Show Azure information")
        return
    
    command = sys.argv[1]
    azure_manager = FaceNetAzure()
    
    if command == 'deploy':
        azure_manager.deploy_to_azure()
    elif command == 'vm':
        azure_manager.create_virtual_machine()
    elif command == 'storage':
        azure_manager.create_storage_account()
    elif command == 'sql':
        azure_manager.create_sql_database()
    elif command == 'aks':
        azure_manager.create_kubernetes_service()
    elif command == 'status':
        print("VM Status:")
        print(azure_manager.get_vm_status())
        print("\nStorage Status:")
        print(azure_manager.get_storage_status())
        print("\nSQL Status:")
        print(azure_manager.get_sql_status())
    elif command == 'info':
        info = azure_manager.get_azure_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
