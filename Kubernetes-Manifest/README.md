# 🛠️ UpTime App - Installation Guide

## 🚀 Deploy on Kubernetes

### 1. Clone the Repository

```bash
git clone https://github.com/niketchandra/UpTime-App.git


2. (Optional) Update Ingress Configuration

cd Kubernetes-Manifest
vi 04-ingress.yaml

Update line no. 10

Replace monitor.abc.live with your custom domain name

Save the file

3. Apply the Manifests

kubectl create ns uptime
kubectl apply -f ./Kubernetes-Manifest/ -n uptime
