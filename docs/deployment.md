# Production Deployment Guide: Google Cloud Run

This guide will walk you through the process of deploying the NOLA PayMongo × GoHighLevel Laravel application to **Google Cloud Run** using the provided `Dockerfile`.

## 1. Prerequisites

Before starting, ensure you have the following installed and configured on your local machine:

1.  **Google Cloud SDK (`gcloud` CLI)**: [Install Guide](https://cloud.google.com/sdk/docs/install)
2.  **Docker CLI**: [Install Guide](https://docs.docker.com/get-docker/)
3.  A **Google Cloud Project** with billing enabled.

Inside your Google Cloud Project, enable the following APIs:

- Cloud Run API
- Artifact Registry API
- Cloud SQL Admin API (if using Cloud SQL)
- Secret Manager API (recommended for storing `.env`)

---

## 2. Environment Variables (.env)

Cloud Run instances are stateless. Instead of pushing your `.env` file to the container, you provide environment variables to the Cloud Run service during deployment.

**Important Production Variables:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-cloud-run-domain.a.run.app
LOG_CHANNEL=stderr # CRITICAL: Cloud Run logs to stderr

# Database (See Step 3)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# The rest of your PayMongo and GHL variables...
```

_Tip: For maximum security, store sensitive values (like API keys and DB passwords) in **Google Secret Manager** and reference them in your Cloud Run deployment._

---

## 3. Database: Google Cloud SQL Connection

If you are using **Google Cloud SQL (MySQL)**, Cloud Run connects via a built-in Unix socket, not a public IP address.

Your `.env` database configuration must look like this:

```env
DB_CONNECTION=mysql
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
DB_SOCKET=/cloudsql/YOUR_PROJECT_ID:REGION:YOUR_INSTANCE_NAME
```

_(Leave `DB_HOST` and `DB_PORT` empty when using the Cloud SQL Unix socket)._

---

## 4. Building and Pushing the Docker Image

We use **Google Artifact Registry** to store our Docker images before deploying them to Cloud Run.

### Step 4.1: Create an Artifact Registry Repository

```bash
gcloud artifacts repositories create paymongo-ghl-repo \
    --repository-format=docker \
    --location=us-central1 \
    --description="Docker repository for PayMongo GHL App"
```

### Step 4.2: Authenticate Docker

```bash
gcloud auth configure-docker us-central1-docker.pkg.dev
```

### Step 4.3: Build the Image

Run this from the root of your Laravel project (where the `Dockerfile` is located):

```bash
# Define your image name
export IMAGE_NAME="us-central1-docker.pkg.dev/YOUR_PROJECT_ID/paymongo-ghl-repo/webapp"

# Build the image using your local Docker daemon
docker build -t $IMAGE_NAME .
```

### Step 4.4: Push the Image

```bash
docker push $IMAGE_NAME
```

---

## 5. Deploying to Cloud Run

Now that the image is in Artifact Registry, deploy it to Cloud Run.

```bash
gcloud run deploy paymongo-ghl-service \
    --image=$IMAGE_NAME \
    --region=us-central1 \
    --allow-unauthenticated \
    --port=8080 \
    --set-env-vars="APP_ENV=production,APP_DEBUG=false,LOG_CHANNEL=stderr" \
    --add-cloudsql-instances="YOUR_PROJECT_ID:REGION:YOUR_INSTANCE_NAME"
```

_(Note: You can pass all your PayMongo and GHL keys via `--set-env-vars` or reference secrets exposed by Secret Manager)._

### Deployment Flags Explained:

- `--allow-unauthenticated`: Makes the endpoint publicly accessible (required for webhooks and the iFrame).
- `--port=8080`: Tells Cloud Run to send traffic to port 8080 (which our Dockerfile's Apache server is listening on).
- `--add-cloudsql-instances`: **Crucial** flag that mounts the Cloud SQL Unix socket so Laravel can connect via `DB_SOCKET`.

---

## 6. Post-Deployment Steps

1.  **Run Database Migrations:**
    Because Cloud Run instances are ephemeral, it's best to run migrations via a temporary Cloud Run Job or by connecting to the Cloud SQL instance from your local machine using the Cloud SQL Auth Proxy.

    _Using a Cloud Run Job (Recommended):_

    ```bash
    gcloud run jobs create migrate-db \
      --image $IMAGE_NAME \
      --command "php" \
      --args "artisan,migrate,--force" \
      --add-cloudsql-instances="YOUR_PROJECT_ID:REGION:YOUR_INSTANCE_NAME" \
      --set-env-vars="DB_SOCKET=...,DB_DATABASE=...,etc..."

    gcloud run jobs execute migrate-db --wait
    ```

2.  **Update GHL Custom Provider Configuration:**
    Once Cloud Run provides your live `https://` domain URL, update your Marketplace App in GoHighLevel to point the `queryUrl` and `paymentsUrl` to the new production domain.

3.  **Update PayMongo Webhooks:**
    Go to the PayMongo Dashboard and register your new webhook endpoint (e.g., `https://your-cloud-run-domain.a.run.app/api/webhook/paymongo`). Update your `.env` with the new webhook secret key perfectly.
