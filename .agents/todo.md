# Next Priorities

Based on the overall `task-progress.md` and the `ghl-flow.md` requirements, here are the immediate next priorities to complete the standard payment provider integration for production:

### [ ] 1️⃣ Priority: Error Handling & Edge Cases

> Improve user experience and edge-case resilience before launch.

- [x] **Provider config error detail display** — Show detailed GHL API error messages in the UI when users fail to connect their keys.
- [x] **Currency support beyond PHP** — Ensure the integration safely rejects or handles GHL locations using USD/other currencies since PayMongo primarily supports PHP.

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
