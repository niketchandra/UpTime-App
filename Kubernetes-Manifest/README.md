# 🛠️ UpTime App - Installation Guide

## 🚀 Deploy on Kubernetes

### 1. Clone the Repository

```bash
git clone https://github.com/niketchandra/UpTime-App.git

```
###  2. (Optional) Update Ingress Configuration
```bash
cd Kubernetes-Manifest
vi 04-ingress.yaml
```

Update line no. 10

Replace monitor.abc.live with your custom domain name

Save the file

### 3. Apply the Manifests

```bash
kubectl create ns uptime
kubectl apply -f ./Kubernetes-Manifest/ -n uptime
```

### 4. Access the Application

```bash
kubectl get svc
```

Use the assigned LoadBalancer address to access the application

--------------------------------------------------------------------

🐳 Deploy with Docker Compose
### 1. Clone the Repository

```bash
git clone https://github.com/niketchandra/UpTime-App.git
cd UpTime-App
```

### 2. Start the Containers

```bash
docker-compose up -d
```
### 3. Open in Browser
Visit: http://localhost:8080/install

