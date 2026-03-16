#!/bin/bash

# ==============================================================================
# NOLA PayMongo - GCloud Run Deployment Script
# ==============================================================================
# This script automates the build and deployment process for Google Cloud Run.
# It performs two main steps:
# 1. Builds the Docker container in the cloud using Google Cloud Build.
# 2. Deploys the newly built image to Google Cloud Run.
# ==============================================================================

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PROJECT_ID="nola-paymongo"
REGION="${GCP_REGION:-asia-southeast1}"
REPO_NAME="paymongo-repo"
IMAGE_NAME="paymongo-app"
SERVICE_NAME="paymongo-app"
SQL_INSTANCE="${PROJECT_ID}:${REGION}:paymongo-db-v1"

# Full tag for the docker image in Artifact Registry
IMAGE_TAG="${REGION}-docker.pkg.dev/${PROJECT_ID}/${REPO_NAME}/${IMAGE_NAME}"

echo "🚀 Starting deployment for project: ${PROJECT_ID} → region: ${REGION}..."

# --- Step 1: Build & Upload the New Image ---
echo "🏗️  Step 1: Building and uploading the new image to Artifact Registry..."
gcloud builds submit \
  --tag "${IMAGE_TAG}" \
  --project="${PROJECT_ID}" \
  --region="${REGION}"

# --- Step 2: Release to Cloud Run ---
echo "🚢 Step 2: Releasing the new image to Cloud Run Service..."
gcloud run deploy "${SERVICE_NAME}" \
  --image "${IMAGE_TAG}" \
  --region "${REGION}" \
  --project="${PROJECT_ID}" \
  --allow-unauthenticated \
  --port=8080 \
  --add-cloudsql-instances="${SQL_INSTANCE}"

# --- Step 3: Update the Migration Job ---
# We must update the job to use the new image before executing it.
echo "🔄 Step 3: Updating the migration job image..."
gcloud run jobs update migrate-db \
  --image "${IMAGE_TAG}" \
  --region "${REGION}" \
  --project="${PROJECT_ID}" \
  --command php \
  --args artisan,migrate,--force

# --- Step 4: Run database migrations ---
# Safe to run every deploy — 'migrate' only adds NEW tables/columns.
# It DOES NOT delete your data. (Unlike 'migrate:fresh' which we used earlier).
echo "🗄️  Step 4: Running incremental database migrations..."
gcloud run jobs execute migrate-db \
  --region="${REGION}" \
  --project="${PROJECT_ID}" \
  --wait

echo "🎉 All done! App deployed and database is up to date."
