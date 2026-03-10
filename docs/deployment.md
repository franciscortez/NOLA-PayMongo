# Production Deployment Guide: Google Cloud Run

This guide explains how we successfully deployed the NOLA PayMongo × GoHighLevel Laravel application to **Google Cloud Run** using the provided `Dockerfile`.

## 1. Prerequisites

Ensure you have the following installed and configured on your local machine:

1. **Google Cloud SDK (`gcloud` CLI)**: [Install Guide](https://cloud.google.com/sdk/docs/install)
2. Authenticate and set your active project:

    ```bash
    gcloud auth login
    gcloud config set project your-project-id
    ```

3. **Enable GCP APIs**:
    ```bash
    gcloud services enable \
      run.googleapis.com \
      sqladmin.googleapis.com \
      artifactregistry.googleapis.com \
      cloudbuild.googleapis.com
    ```

---

## 2. Infrastructure Setup

### Create Artifact Registry

```bash
gcloud artifacts repositories create paymongo-repo \
  --repository-format=docker \
  --location=us-central1 \
  --description="PayMongo docker repository"
```

### Create Cloud SQL Database

```bash
# Create the MySQL 8 instance
gcloud sql instances create paymongo-db --database-version=MYSQL_8_0 --tier=db-f1-micro --region=us-central1

# Create the database inside the instance
gcloud sql databases create paymongo --instance=paymongo-db

# Set a secure root password
gcloud sql users set-password root --host=% --instance=paymongo-db --password='YOUR_SUPER_SECURE_PASSWORD'
```

---

## 3. Build & Deploy Image

Submit the codebase to Cloud Build to build and push the docker image automatically:

```bash
gcloud builds submit --tag us-central1-docker.pkg.dev/your-project-id/paymongo-repo/paymongo-app
```

Then, deploy the image immediately to Cloud Run attaching the SQL database via UNIX sockets:

```bash
gcloud run deploy paymongo-app \
  --image us-central1-docker.pkg.dev/your-project-id/paymongo-repo/paymongo-app \
  --region us-central1 \
  --allow-unauthenticated \
  --add-cloudsql-instances your-project-id:us-central1:paymongo-db \
  --set-env-vars="DB_CONNECTION=mysql,DB_SOCKET=/cloudsql/your-project-id:us-central1:paymongo-db,DB_DATABASE=paymongo,DB_USERNAME=root,DB_PASSWORD=YOUR_SUPER_SECURE_PASSWORD" \
  --set-env-vars="APP_ENV=production,APP_DEBUG=true,APP_KEY=YOUR_APP_KEY" \
  # ... attach the rest of your GHL and PayMongo variables here ...
```

_Note: Store highly sensitive variables in Secret Manager rather than plain environment variables in production long-term._

---

## 4. Retrieve APP_URL & Run Migrations

Once your service is deployed, retrieve your live domain URL from Cloud Run (e.g. `https://paymongo-app-1234.us-central1.run.app`) and dynamically update the configuration:

```bash
gcloud run services update paymongo-app \
  --region us-central1 \
  --update-env-vars="APP_URL=https://paymongo-app-1234.us-central1.run.app"
```

Then run the database migrations via a Google Cloud Run Job:

```bash
# Create the Job
gcloud run jobs create migrate-db \
  --image us-central1-docker.pkg.dev/your-project-id/paymongo-repo/paymongo-app \
  --command="php" \
  --args="artisan,migrate,--force" \
  --set-cloudsql-instances your-project-id:us-central1:paymongo-db \
  --set-env-vars="DB_CONNECTION=mysql,DB_SOCKET=/cloudsql/your-project-id:us-central1:paymongo-db,DB_DATABASE=paymongo,DB_USERNAME=root,DB_PASSWORD=YOUR_SUPER_SECURE_PASSWORD" \
  --region us-central1

# Execute the Job
gcloud run jobs execute migrate-db --region us-central1 --wait
```

---

## 5. Webhook Configurations

Update your external integration endpoints using your new live `APP_URL`.

**1. Register PayMongo Live Webhooks**
Use the included command line configuration tool:

```bash
# Register the webhook to run directly against the live URL
COMPOSER_ALLOW_SUPERUSER=1 APP_URL=https://paymongo-app-1234.us-central1.run.app \
php artisan paymongo:register-webhook
```

This generates a new `whsk_XXXX` key. Add this secret to your Cloud Run environment variable as `PAYMONGO_WEBHOOK_SECRET` and into your local `.env`.

**2. Update GoHighLevel App Redirect URLs**
In GoHighLevel Marketplace settings, update the redirect/oauth callback path to:
`https://paymongo-app-1234.us-central1.run.app/oauth/callback`

---

## Troubleshooting Known Issues

### `ERR_TOO_MANY_ACCEPT_CH_RESTARTS` (Infinite URL Redirects)

**Symptom:** Opening the page results in an infinite 301 loop.

**Cause:** Google Cloud Run terminates SSL upstream and proxies the traffic to the container using `HTTP`. Since Laravel only sees the `HTTP` incoming connection, any manually written `EnsureHttps` middleware will perpetually redirect to `HTTPS`.

**Fix:** We removed the custom `EnsureHttps` middleware inside `bootstrap/app.php` and modified `app/Providers/AppServiceProvider.php` to dynamically force the scheme instead:

```php
public function boot(): void
{
    if ($this->app->environment('production')) {
        URL::forceScheme('https');
    }
}
```
