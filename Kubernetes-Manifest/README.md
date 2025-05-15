# Uptime - Cronjob & Monitoring software

🛠️ Installation Options

<strong>Deploy on Kubernetes</stromg>

1. Clone the Repository

    git clone https://github.com/niketchandra/UpTime-App.git<br>
    
    <OPTIONAL STEP>

    cd Kubernetes-Manifest<br>
    vi 04-ingress.yaml<br>
    update line no. 10<br>
    Replace "monitor.abc.live" with your domain name and <SAVE>

2. Apply the Manifests
   <br>
   <br>
    kubectl create ns uptime <br>
    kubectl apply -f .\Kubernetes-Manifest\ -n uptime

4. Access the Application
    
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
    
    Visit: http://localhost:8080/install

