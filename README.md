# MaGo

**From Magento to Go**: a learning-in-public portfolio project.

> M·AI·La·Sy·Py·Go: from **M**agento towards **La**ravel, **Sy**mfony, **Py**thon and **Go**, glued together with **AI**.

After 19 years of backend PHP, mostly enterprise Magento 2, API integrations and application security,
I want to spend 16 weeks building one small but realistic system with the most useful tools I see on
the job, designed from day one to be fast and secure. Every service is real, tested and measurable.

## The system

A small marketplace with a catalog, orders and supplier sync.

| Service | Stack | Responsibility |
|---|---|---|
| `services/symfony-api` | Symfony, Doctrine, Messenger, JWT | Catalog, orders, users (REST `/v1`) |
| `services/laravel-shop` | Laravel, Filament | Storefront + admin panel, talks to the API over REST |
| `services/ai-python` | FastAPI, SQLAlchemy | Product enrichment, semantic search, order assistant |
| `services/go-sync` | Go | Supplier prices & stock, consumes events, updates the API |
| infra | Docker Compose, RabbitMQ, Redis, PostgreSQL | Events (e.g. `OrderPlaced`), cache, storage |

## Principles (with measurable proof)

1. **Speed**: API p95 < 200 ms, measured with k6. Redis, ETags, indexes, pagination, heavy work in queues, Go for high volume.
2. **Security**: per-key scoped tokens, rate limiting, input validation, security headers/CSP, no secrets in code,
   `composer audit` / `pip-audit` / `govulncheck` in CI, prompt-injection defences, an OWASP checklist in the repo.
   All commits are signed.
3. **Third-party integration**: OpenAPI for every service, versioned APIs (`/v1`), HMAC-signed webhooks with retries,
   idempotency keys, one error format everywhere, plus an example external integration.

## Roadmap

- [ ] Weeks 1-4: Symfony API
- [ ] Weeks 5-7: Laravel storefront + admin
- [ ] Weeks 8-11: Applied AI service (Python)
- [ ] Weeks 12-15: Go sync service
- [ ] Week 16: one `docker compose up`, demo video

## How I work

Each feature starts from a `SPEC.md` I write myself. AI (Claude Code) is a pair programmer and reviewer, not an autopilot:
every change must pass tests and PHPStan, and once a week I solve an exercise with no AI at all.

## Run it

_Coming soon: `docker compose up`._
