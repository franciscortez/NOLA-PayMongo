# Next Priorities

Based on the overall `task-progress.md` and the `ghl-flow.md` requirements, here are the immediate next priorities to complete the standard payment provider integration for production:

### [ ] 1️⃣ Priority: Error Handling & Edge Cases

> Improve user experience and edge-case resilience before launch.

- [x] **Provider config error detail display** — Show detailed GHL API error messages in the UI when users fail to connect their keys.
- [ ] **Currency support beyond PHP** — Ensure the integration safely rejects or handles GHL locations using USD/other currencies since PayMongo primarily supports PHP.
- [ ] **Checkout timeout configuration** — Make the 30-second checkout handshake timeout duration configurable via `.env`.

### [ ] 2️⃣ Priority: Preparation for Production (GCP & Cloud Run)

> Deployment pipeline and environment configuration.

- [ ] **Dockerization** — Create a `Dockerfile` optimized for Laravel 12 on Google Cloud Run.

### [ ] 3️⃣ Priority: Documentation & Testing

> Project documentation and test coverage.

- [ ] **README.md** — Setup instructions, environment requirements, architecture overview
- [ ] **API documentation** — Document all endpoints (Postman collection or OpenAPI spec)
- [ ] **Unit tests** — Test services (PayMongoService, GhlService, ProviderConfigService)
- [ ] **Integration tests** — End-to-end test for OAuth → checkout → payment → verify flow
- [ ] **Webhook testing** — Mock PayMongo webhook events for automated testing
- [ ] **GHL sandbox testing guide** — Step-by-step guide for testing the full flow on GHL sandbox
