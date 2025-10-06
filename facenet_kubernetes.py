#!/usr/bin/env python3
"""
FaceNet Kubernetes Support

This script provides Kubernetes support for FaceNet service.
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

class FaceNetKubernetes:
    """Kubernetes manager for FaceNet service."""
    
    def __init__(self):
        """Initialize Kubernetes manager."""
        self.namespace = 'facenet'
        self.app_name = 'facenet'
        self.image_name = 'facenet:latest'
        self.port = 8080
        self.replicas = 1
    
    def create_namespace(self) -> bool:
        """Create Kubernetes namespace."""
        try:
            print("Creating Kubernetes namespace...")
            result = subprocess.run(['kubectl', 'create', 'namespace', self.namespace], 
                                  capture_output=True, text=True)
            if result.returncode != 0 and 'already exists' not in result.stderr:
                print(f"✗ Error creating namespace: {result.stderr}")
                return False
            
            print(f"✓ Namespace {self.namespace} created")
            return True
        except Exception as e:
            print(f"✗ Error creating namespace: {e}")
            return False
    
    def create_deployment(self) -> bool:
        """Create Kubernetes deployment."""
        try:
            deployment_yaml = f"""apiVersion: apps/v1
kind: Deployment
metadata:
  name: {self.app_name}
  namespace: {self.namespace}
  labels:
    app: {self.app_name}
spec:
  replicas: {self.replicas}
  selector:
    matchLabels:
      app: {self.app_name}
  template:
    metadata:
      labels:
        app: {self.app_name}
    spec:
      containers:
      - name: {self.app_name}
        image: {self.image_name}
        ports:
        - containerPort: {self.port}
        env:
        - name: PYTHONPATH
          value: "/app"
        - name: PYTHONUNBUFFERED
          value: "1"
        resources:
          requests:
            memory: "2Gi"
            cpu: "1000m"
          limits:
            memory: "4Gi"
            cpu: "2000m"
        volumeMounts:
        - name: logs
          mountPath: /app/logs
        - name: debug-images
          mountPath: /app/debug_images
        - name: backups
          mountPath: /app/backups
        - name: facenet-models
          mountPath: /app/facenet-master
        livenessProbe:
          httpGet:
            path: /health
            port: {self.port}
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: {self.port}
          initialDelaySeconds: 5
          periodSeconds: 5
      volumes:
      - name: logs
        emptyDir: {{}}
      - name: debug-images
        emptyDir: {{}}
      - name: backups
        emptyDir: {{}}
      - name: facenet-models
        emptyDir: {{}}
"""
            
            with open('facenet-deployment.yaml', 'w') as f:
                f.write(deployment_yaml)
            
            print("Creating Kubernetes deployment...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-deployment.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating deployment: {result.stderr}")
                return False
            
            print(f"✓ Deployment {self.app_name} created")
            return True
        except Exception as e:
            print(f"✗ Error creating deployment: {e}")
            return False
    
    def create_service(self) -> bool:
        """Create Kubernetes service."""
        try:
            service_yaml = f"""apiVersion: v1
kind: Service
metadata:
  name: {self.app_name}-service
  namespace: {self.namespace}
  labels:
    app: {self.app_name}
spec:
  selector:
    app: {self.app_name}
  ports:
  - protocol: TCP
    port: {self.port}
    targetPort: {self.port}
  type: ClusterIP
"""
            
            with open('facenet-service.yaml', 'w') as f:
                f.write(service_yaml)
            
            print("Creating Kubernetes service...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-service.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating service: {result.stderr}")
                return False
            
            print(f"✓ Service {self.app_name}-service created")
            return True
        except Exception as e:
            print(f"✗ Error creating service: {e}")
            return False
    
    def create_ingress(self) -> bool:
        """Create Kubernetes ingress."""
        try:
            ingress_yaml = f"""apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: {self.app_name}-ingress
  namespace: {self.namespace}
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
spec:
  rules:
  - host: facenet.local
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: {self.app_name}-service
            port:
              number: {self.port}
"""
            
            with open('facenet-ingress.yaml', 'w') as f:
                f.write(ingress_yaml)
            
            print("Creating Kubernetes ingress...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-ingress.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating ingress: {result.stderr}")
                return False
            
            print(f"✓ Ingress {self.app_name}-ingress created")
            return True
        except Exception as e:
            print(f"✗ Error creating ingress: {e}")
            return False
    
    def create_configmap(self) -> bool:
        """Create Kubernetes ConfigMap."""
        try:
            configmap_yaml = f"""apiVersion: v1
kind: ConfigMap
metadata:
  name: {self.app_name}-config
  namespace: {self.namespace}
data:
  PYTHONPATH: "/app"
  PYTHONUNBUFFERED: "1"
  LOG_LEVEL: "INFO"
  DEBUG: "false"
"""
            
            with open('facenet-configmap.yaml', 'w') as f:
                f.write(configmap_yaml)
            
            print("Creating Kubernetes ConfigMap...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-configmap.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating ConfigMap: {result.stderr}")
                return False
            
            print(f"✓ ConfigMap {self.app_name}-config created")
            return True
        except Exception as e:
            print(f"✗ Error creating ConfigMap: {e}")
            return False
    
    def create_secret(self) -> bool:
        """Create Kubernetes Secret."""
        try:
            secret_yaml = f"""apiVersion: v1
kind: Secret
metadata:
  name: {self.app_name}-secret
  namespace: {self.namespace}
type: Opaque
data:
  # Base64 encoded values
  # Example: echo -n "your-secret" | base64
  database-password: cm9vdHBhc3N3b3Jk
  api-key: eW91ci1hcGkta2V5
"""
            
            with open('facenet-secret.yaml', 'w') as f:
                f.write(secret_yaml)
            
            print("Creating Kubernetes Secret...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-secret.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating Secret: {result.stderr}")
                return False
            
            print(f"✓ Secret {self.app_name}-secret created")
            return True
        except Exception as e:
            print(f"✗ Error creating Secret: {e}")
            return False
    
    def create_persistent_volume(self) -> bool:
        """Create Kubernetes PersistentVolume."""
        try:
            pv_yaml = f"""apiVersion: v1
kind: PersistentVolume
metadata:
  name: {self.app_name}-pv
  namespace: {self.namespace}
spec:
  capacity:
    storage: 10Gi
  accessModes:
    - ReadWriteOnce
  persistentVolumeReclaimPolicy: Retain
  storageClassName: {self.app_name}-storage
  hostPath:
    path: /data/facenet
"""
            
            with open('facenet-pv.yaml', 'w') as f:
                f.write(pv_yaml)
            
            print("Creating Kubernetes PersistentVolume...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-pv.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating PersistentVolume: {result.stderr}")
                return False
            
            print(f"✓ PersistentVolume {self.app_name}-pv created")
            return True
        except Exception as e:
            print(f"✗ Error creating PersistentVolume: {e}")
            return False
    
    def create_persistent_volume_claim(self) -> bool:
        """Create Kubernetes PersistentVolumeClaim."""
        try:
            pvc_yaml = f"""apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: {self.app_name}-pvc
  namespace: {self.namespace}
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 10Gi
  storageClassName: {self.app_name}-storage
"""
            
            with open('facenet-pvc.yaml', 'w') as f:
                f.write(pvc_yaml)
            
            print("Creating Kubernetes PersistentVolumeClaim...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-pvc.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating PersistentVolumeClaim: {result.stderr}")
                return False
            
            print(f"✓ PersistentVolumeClaim {self.app_name}-pvc created")
            return True
        except Exception as e:
            print(f"✗ Error creating PersistentVolumeClaim: {e}")
            return False
    
    def create_horizontal_pod_autoscaler(self) -> bool:
        """Create Kubernetes HorizontalPodAutoscaler."""
        try:
            hpa_yaml = f"""apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: {self.app_name}-hpa
  namespace: {self.namespace}
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: {self.app_name}
  minReplicas: 1
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
"""
            
            with open('facenet-hpa.yaml', 'w') as f:
                f.write(hpa_yaml)
            
            print("Creating Kubernetes HorizontalPodAutoscaler...")
            result = subprocess.run(['kubectl', 'apply', '-f', 'facenet-hpa.yaml'], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error creating HorizontalPodAutoscaler: {result.stderr}")
                return False
            
            print(f"✓ HorizontalPodAutoscaler {self.app_name}-hpa created")
            return True
        except Exception as e:
            print(f"✗ Error creating HorizontalPodAutoscaler: {e}")
            return False
    
    def deploy_all(self) -> bool:
        """Deploy all Kubernetes resources."""
        try:
            print("Deploying FaceNet to Kubernetes...")
            
            # Create namespace
            if not self.create_namespace():
                return False
            
            # Create ConfigMap
            if not self.create_configmap():
                return False
            
            # Create Secret
            if not self.create_secret():
                return False
            
            # Create PersistentVolume
            if not self.create_persistent_volume():
                return False
            
            # Create PersistentVolumeClaim
            if not self.create_persistent_volume_claim():
                return False
            
            # Create deployment
            if not self.create_deployment():
                return False
            
            # Create service
            if not self.create_service():
                return False
            
            # Create ingress
            if not self.create_ingress():
                return False
            
            # Create HPA
            if not self.create_horizontal_pod_autoscaler():
                return False
            
            print("✓ FaceNet deployed to Kubernetes successfully")
            return True
        except Exception as e:
            print(f"✗ Error deploying to Kubernetes: {e}")
            return False
    
    def delete_all(self) -> bool:
        """Delete all Kubernetes resources."""
        try:
            print("Deleting FaceNet from Kubernetes...")
            
            # Delete all resources
            resources = [
                'facenet-hpa.yaml',
                'facenet-ingress.yaml',
                'facenet-service.yaml',
                'facenet-deployment.yaml',
                'facenet-pvc.yaml',
                'facenet-pv.yaml',
                'facenet-secret.yaml',
                'facenet-configmap.yaml'
            ]
            
            for resource in resources:
                if os.path.exists(resource):
                    result = subprocess.run(['kubectl', 'delete', '-f', resource], 
                                          capture_output=True, text=True)
                    if result.returncode != 0:
                        print(f"⚠ Warning: Error deleting {resource}: {result.stderr}")
            
            # Delete namespace
            result = subprocess.run(['kubectl', 'delete', 'namespace', self.namespace], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"⚠ Warning: Error deleting namespace: {result.stderr}")
            
            print("✓ FaceNet deleted from Kubernetes")
            return True
        except Exception as e:
            print(f"✗ Error deleting from Kubernetes: {e}")
            return False
    
    def get_deployment_status(self) -> Dict:
        """Get deployment status."""
        try:
            result = subprocess.run(['kubectl', 'get', 'deployment', self.app_name, '-n', self.namespace, '-o', 'json'], 
                                  capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            deployment_info = json.loads(result.stdout)
            return {
                'name': deployment_info['metadata']['name'],
                'namespace': deployment_info['metadata']['namespace'],
                'replicas': deployment_info['spec']['replicas'],
                'ready_replicas': deployment_info['status'].get('readyReplicas', 0),
                'available_replicas': deployment_info['status'].get('availableReplicas', 0),
                'status': deployment_info['status'].get('conditions', [])
            }
        except Exception as e:
            return {'error': str(e)}
    
    def get_pod_status(self) -> List[Dict]:
        """Get pod status."""
        try:
            result = subprocess.run(['kubectl', 'get', 'pods', '-l', f'app={self.app_name}', '-n', self.namespace, '-o', 'json'], 
                                  capture_output=True, text=True)
            
            if result.returncode != 0:
                return [{'error': result.stderr}]
            
            pods_info = json.loads(result.stdout)
            pods = []
            
            for pod in pods_info['items']:
                pods.append({
                    'name': pod['metadata']['name'],
                    'namespace': pod['metadata']['namespace'],
                    'status': pod['status']['phase'],
                    'ready': pod['status'].get('conditions', []),
                    'restarts': sum(container.get('restartCount', 0) for container in pod['status'].get('containerStatuses', []))
                })
            
            return pods
        except Exception as e:
            return [{'error': str(e)}]
    
    def get_service_status(self) -> Dict:
        """Get service status."""
        try:
            result = subprocess.run(['kubectl', 'get', 'service', f'{self.app_name}-service', '-n', self.namespace, '-o', 'json'], 
                                  capture_output=True, text=True)
            
            if result.returncode != 0:
                return {'error': result.stderr}
            
            service_info = json.loads(result.stdout)
            return {
                'name': service_info['metadata']['name'],
                'namespace': service_info['metadata']['namespace'],
                'type': service_info['spec']['type'],
                'cluster_ip': service_info['spec']['clusterIP'],
                'ports': service_info['spec']['ports']
            }
        except Exception as e:
            return {'error': str(e)}
    
    def get_logs(self, pod_name: str = None, lines: int = 50) -> str:
        """Get pod logs."""
        try:
            if pod_name is None:
                # Get first pod
                pods = self.get_pod_status()
                if not pods or 'error' in pods[0]:
                    return "No pods found"
                pod_name = pods[0]['name']
            
            result = subprocess.run(['kubectl', 'logs', pod_name, '-n', self.namespace, '--tail', str(lines)], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                return f"Error getting logs: {result.stderr}"
            
            return result.stdout
        except Exception as e:
            return f"Error getting logs: {e}"
    
    def scale_deployment(self, replicas: int) -> bool:
        """Scale deployment."""
        try:
            print(f"Scaling deployment to {replicas} replicas...")
            result = subprocess.run(['kubectl', 'scale', 'deployment', self.app_name, '--replicas', str(replicas), '-n', self.namespace], 
                                  capture_output=True, text=True)
            if result.returncode != 0:
                print(f"✗ Error scaling deployment: {result.stderr}")
                return False
            
            print(f"✓ Deployment scaled to {replicas} replicas")
            return True
        except Exception as e:
            print(f"✗ Error scaling deployment: {e}")
            return False
    
    def get_kubernetes_info(self) -> Dict:
        """Get comprehensive Kubernetes information."""
        try:
            info = {
                'namespace': self.namespace,
                'app_name': self.app_name,
                'image_name': self.image_name,
                'port': self.port,
                'replicas': self.replicas,
                'deployment_status': self.get_deployment_status(),
                'pod_status': self.get_pod_status(),
                'service_status': self.get_service_status()
            }
            
            return info
        except Exception as e:
            return {
                'error': str(e)
            }

def main():
    """Main function."""
    print("FaceNet Kubernetes Manager")
    print("=" * 50)
    
    if len(sys.argv) < 2:
        print("Usage: python facenet_kubernetes.py <command>")
        print("Commands:")
        print("  deploy      - Deploy FaceNet to Kubernetes")
        print("  delete      - Delete FaceNet from Kubernetes")
        print("  status      - Show deployment status")
        print("  pods        - Show pod status")
        print("  service     - Show service status")
        print("  logs        - Show pod logs")
        print("  scale       - Scale deployment")
        print("  info        - Show Kubernetes information")
        return
    
    command = sys.argv[1]
    k8s_manager = FaceNetKubernetes()
    
    if command == 'deploy':
        k8s_manager.deploy_all()
    elif command == 'delete':
        k8s_manager.delete_all()
    elif command == 'status':
        status = k8s_manager.get_deployment_status()
        print(f"Deployment Status: {status}")
    elif command == 'pods':
        pods = k8s_manager.get_pod_status()
        print(f"Pod Status: {pods}")
    elif command == 'service':
        service = k8s_manager.get_service_status()
        print(f"Service Status: {service}")
    elif command == 'logs':
        logs = k8s_manager.get_logs()
        print(logs)
    elif command == 'scale':
        if len(sys.argv) < 3:
            print("Usage: python facenet_kubernetes.py scale <replicas>")
            return
        replicas = int(sys.argv[2])
        k8s_manager.scale_deployment(replicas)
    elif command == 'info':
        info = k8s_manager.get_kubernetes_info()
        print(json.dumps(info, indent=2))
    else:
        print(f"Unknown command: {command}")

if __name__ == '__main__':
    main()
