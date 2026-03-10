# Next Priorities

Based on the overall `task-progress.md` and the `ghl-flow.md` requirements, here are the immediate next priorities to complete the standard payment provider integration for production:

### [x] 1️⃣ Priority: Invoice Payments

> Handle payments for GHL Invoices via PayMongo.

**Prerequisites (GHL Marketplace Setup):**

- [x] Update App OAuth scopes to include `invoices.readonly` and `invoices.write`

**Development Tasks:**

- [x] Investigate if invoices use the same `paymentsUrl` checkout flow or require separate API logic
- [x] Map GHL Invoice payload to PayMongo Checkout Session

### [ ] 2️⃣ Priority: Error Handling & Edge Cases

### [ ] 2️⃣ Priority: Preparation for Production (GCP & Cloud Run)

> Deployment pipeline and environment configuration.

- [x] **Dockerization** — Create a `Dockerfile` optimized for Laravel 12 on Google Cloud Run.

### [ ] 3️⃣ Priority: Documentation & Testing

> Project documentation and test coverage.

- [x] **README.md** — Setup instructions, environment requirements, architecture overview
- [x] **API documentation** — Document all endpoints (Postman collection or OpenAPI spec)
- [x] **Unit tests** — Test services (PayMongoService, GhlService, ProviderConfigService)
- [x] **Integration tests** — End-to-end test for OAuth → checkout → payment → verify flow
- [x] **Webhook testing** — Mock PayMongo webhook events for automated testing
- [x] **GHL sandbox testing guide** — Step-by-step guide for testing the full flow on GHL sandbox
