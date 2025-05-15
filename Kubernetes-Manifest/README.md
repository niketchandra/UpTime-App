<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>🛠️ UpTime App - Installation Guide</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 40px;
      background-color: #f9f9f9;
      color: #333;
    }
    h1 {
      color: #2c3e50;
    }
    h2 {
      color: #2980b9;
    }
    code {
      background-color: #eee;
      padding: 2px 6px;
      border-radius: 4px;
    }
    pre {
      background-color: #eee;
      padding: 10px;
      border-radius: 6px;
      overflow-x: auto;
    }
    .section {
      margin-bottom: 40px;
    }
  </style>
</head>
<body>

  <h1>🛠️ Installation Options</h1>

  <div class="section">
    <h2>🚀 Deploy on Kubernetes</h2>

    <h3>1. Clone the Repository</h3>
    <pre><code>git clone https://github.com/niketchandra/UpTime-App.git</code></pre>

    <h3>2. Optional: Update Ingress Domain</h3>
    <pre><code>cd Kubernetes-Manifest
vi 04-ingress.yaml</code></pre>
    <p>Update <strong>line no. 10</strong>:</p>
    <p>Replace <code>monitor.abc.live</code> with your actual domain name and <strong>SAVE</strong>.</p>

    <h3>3. Apply the Manifests</h3>
    <pre><code>kubectl create ns uptime
kubectl apply -f .\Kubernetes-Manifest\ -n uptime</code></pre>

    <h3>4. Access the Application</h3>
    <pre><code>kubectl get svc</code></pre>
    <p>Use the assigned <strong>LoadBalancer</strong> address to access the app.</p>
  </div>

  <div class="section">
    <h2>🐳 Deploy with Docker Compose</h2>

    <h3>1. Clone the Repository</h3>
    <pre><code>git clone https://github.com/niketchandra/UpTime-App.git
cd UpTime-App</code></pre>

    <h3>2. Start the Containers</h3>
    <pre><code>docker-compose up -d</code></pre>

    <h3>3. Open in Browser</h3>
    <p>Visit: <a href="http://localhost:8080/install" target="_blank">http://localhost:8080/install</a></p>
  </div>

</body>
</html>
