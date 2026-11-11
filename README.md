# MaGo

**From Magento to Go**: a learning-in-public portfolio project.

> M·AI·La·Sy·Py·Go: from **M**agento towards **La**ravel, **Sy**mfony, **Py**thon and **Go**, glued together with **AI**.

After 19 years of backend PHP, mostly enterprise Magento 2, API integrations and application security,
I want to spend 16 weeks building one small but realistic system with the most useful tools I see on
the job, designed from day one to be fast and secure. Every service is real, tested and measurable.

## The system

A small marketplace with a catalog, orders and supplier sync.

What a user can do:

- Browse a catalog full of products and place orders.
- Shop on a store structure similar to Magento: website > store > store view.
- Buy products in easy, clear, fast and safe steps, with the option to check out as a guest (no registration).
- Pay and return products safely and simply.

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

Requirements: Docker with Compose v2.

```bash
cp .env.example .env          # then set real values (see below)
docker compose up -d --build  # API on http://127.0.0.1:8080
docker compose ps             # every service must be "healthy"
```

`GET /health` is the liveness check used by Docker: `200 {"status":"ok"}`, `Cache-Control: no-store, private`,
no database access (the database has its own healthcheck).

## Configuration and secrets

No secret is ever committed. All local configuration lives in **one file, `.env` in the repo root**, which is
git-ignored. `.env.example` is its committed template, with dummy values: copy it and change every `change-me`.

| Variable | Used by | How to set it |
|---|---|---|
| `POSTGRES_DB`, `POSTGRES_USER` | PostgreSQL, symfony-api | Any name, e.g. `mago` |
| `POSTGRES_PASSWORD` | PostgreSQL, symfony-api | `openssl rand -hex 16` (letters and digits only: it goes into a URL) |
| `APP_SECRET` | symfony-api | `openssl rand -hex 16` |
| `API_HTTP_PORT` | nginx (host port, bound to 127.0.0.1) | Default `8080` |
| `UID`, `GID` | PHP container user | `id -u` / `id -g`, so generated files stay yours |

`docker-compose.yml` reads these values and passes them to the containers; it builds `DATABASE_URL` for Symfony.
If a required value is missing, `docker compose` stops with an error that names it.
The `.env` files inside `services/symfony-api/` are Symfony's committed defaults: they contain no secrets and no
`DATABASE_URL`. Never put real values there; use the root `.env`.

When a new service or secret is added, it gets a line in `.env.example` and in this table.
