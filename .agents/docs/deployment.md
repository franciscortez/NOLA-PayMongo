# Production Deployment Guide: Google Cloud Run (Singapore)

This guide explained how we successfully deployed the NOLA PayMongo × GoHighLevel Laravel application to **Google Cloud Run** in Singapore (`asia-southeast1`) using the automated deployment pipeline.

## 1. Infrastructure Overview (Verified)

| Component | Resource Name | Specs | Region |
|---|---|---|---|
| **Project ID** | `nola-paymongo` | — | — |
| **Artifact Registry** | `paymongo-repo` | — | `asia-southeast1` |
| **Cloud SQL Instance** | `paymongo-db-v1` | MySQL 8.0, Enterprise, `db-f1-micro` (0.6GB RAM, 10GB SSD) | `asia-southeast1` |
| **Cloud Run Service** | `paymongo-app` | 1 vCPU, 512Mi Memory | `asia-southeast1` |
| **Cloud Run Job** | `migrate-db` | 1 vCPU, 512Mi Memory | `asia-southeast1` |

## 2. Live Production URLs

- **App Home**: `https://paymongo-app-7msuanjg5a-as.a.run.app`
- **GHL Callback**: `https://paymongo-app-7msuanjg5a-as.a.run.app/oauth/callback`
- **PayMongo Webhook**: `https://paymongo-app-7msuanjg5a-as.a.run.app/api/webhook/paymongo`

---

## 3. Deployment Workflow

We use a 3-step automated pipeline via `.agents/scripts/deploy.sh`.

### Step 1: Build & Upload
Submits the source code to **Google Cloud Build**. It builds the 3-stage Docker image (Node → Composer → Apache) and pushes it to **Artifact Registry**.

### Step 2: Release to Cloud Run
Deploys the new image to **Cloud Run**, attaching the **Cloud SQL** instance via Unix sockets (`/cloudsql/nola-paymongo:asia-southeast1:paymongo-db-v1`).

### Step 3: Database Migrations
Executes the `migrate-db` **Cloud Run Job**. This runs `php artisan migrate --force` against the live database to ensures the schema is up to date.

---

## 4. How to Redeploy

Simply run the script from the root directory:

```bash
./.agents/scripts/deploy.sh
```

The script is configured to use `asia-southeast1` and the production `nola-paymongo` project by default.

---

## 5. Environment Management

Production environment variables are managed directly in **Cloud Run**, not via a `.env` file in the container.

### To Update an Environment Variable:
```bash
gcloud run services update paymongo-app \
  --region=asia-southeast1 \
  --project=nola-paymongo \
  --update-env-vars="KEY=VALUE,ANOTHER_KEY=VALUE"
```

### Critical Variables:
- `PAYMONGO_IS_PRODUCTION`: Set to `true` for live payments.
- `PAYMONGO_WEBHOOK_SECRET`: Optional legacy fallback. Webhooks are now provisioned dynamically per location.
- `DB_PASSWORD`: The root password for `paymongo-db-v1`.

---

---

## 6. Automated Webhook Provisioning

The application automatically manages webhooks on the PayMongo side for each GHL location during the connection process. This ensures merchant isolation and unique signatures across all sub-accounts.

Manual registration is no longer required and the `paymongo:register-webhook` command has been removed.

---

## 7. Automated Log Cleanup (Cloud Scheduler)

To keep the Cloud SQL storage optimized (10GB limit), we use Laravel's pruning to delete `webhook_logs` older than 10 days.

### Step 1: Create a Cleanup Job in Cloud Run
The `deploy.sh` script already handles the code part. To trigger it from the cloud:

1.  **Create a Cloud Run Job**:
    ```bash
    gcloud run jobs create cleanup-logs \
      --image asia-southeast1-docker.pkg.dev/nola-paymongo/paymongo-repo/paymongo-app \
      --region asia-southeast1 \
      --command php \
      --args artisan,logs:cleanup \
      --add-cloudsql-instances=nola-paymongo:asia-southeast1:paymongo-db-v1
    ```

2.  **Create a Cloud Scheduler Trigger**:
    Go to **Cloud Scheduler** in the Google Cloud Console and create a job:
    - **Name**: `trigger-log-cleanup`
    - **Frequency**: `0 0 * * *` (Every day at midnight)
    - **Target type**: HTTP
    - **URL**: `https://asia-southeast1-run.googleapis.com/apis/run.googleapis.com/v1/namespaces/nola-paymongo/jobs/cleanup-logs:run`
    - **HTTP method**: POST
    - **Auth header**: Add OAuth token
    - **Service account**: Use the same service account as your Cloud Run app.

---

## 8. Troubleshooting

- **Logs**: View live logs in GCP Console under **Cloud Run > paymongo-app > Logs**.
- **Database Connection**: Ensure the service has the `Cloud SQL Client` IAM role (automatically handled by `gcloud run deploy --add-cloudsql-instances`).
- **Migrations**: If a migration fails, check the **Cloud Run Jobs** execution history for the `migrate-db` job.
