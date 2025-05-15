# Uptime - Cronjob & Monitoring software

🛠️ Installation Options

<strong>Deploy on Kubernetes</stromg>

1. Clone the Repository

    git clone https://github.com/niketchandra/UpTime-App.git

2. Apply the Manifests
    kubectl create ns uptime <br>
    kubectl apply -f .\Kubernetes-Manifest\ -n uptime

3. Access the Application

    kubectl get svc

Use the assigned LoadBalancer

--------------------------------------------------------------------------------------------

<stromg>Deploy with Docker Compose</stromg>

1. Clone the Repository

    git clone https://github.com/niketchandra/UpTime-App.git
    <br> cd UpTime-App

2. Start the Containers

    docker-compose up -d

3. Open in Browser
    
    Visit: http://localhost:8080

