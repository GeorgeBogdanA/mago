# MaGo

Monorepo, learning project. Read `README.md` for the goal and principles.
Personal context (plan, status) lives outside the repo in `/mnt/c/LAVORO/### CLAUDE ###/`: read `STATUS.md` at the start, never write secrets there.
`STATUS.md` stays short (where we are, next step); the history (what was done, when, with what outcome) goes in `LOG.md` in the same folder.

## Layout
- `services/symfony-api`: Symfony API (weeks 1-4), the only service that exists for now
- `docs/specs/`: one SPEC.md per feature, written by Bogdan; `_TEMPLATE.md` is the model

## Rules
- Work from the spec named in the task. If something is not in the spec, ask; do not invent scope.
- Every feature ships with its tests.
- A spec has at most 8 acceptance criteria; the rest goes under Deferred.
- New concepts: use the Learning style and leave `TODO(human)` for real design decisions.
- Comments written by Bogdan start with `HUMAN:` (e.g. `// HUMAN: ...`). Keep them; never reword or delete them without asking. Claude's comments never use that prefix.
- Every change must end with a check that passes, shown with command + output (tests, PHPStan).
- Code, commits, comments and docs in English. Conventional commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).
- Never commit secrets; config via env vars. All local values live in the git-ignored root `.env`; its template is `.env.example` (dummy values). A new variable goes in `.env.example` and in the README table "Configuration and secrets". `DATABASE_URL` is built by `docker-compose.yml`, never written in a `.env` file.

## Commands
<!-- Keep them exact. -->
- Tests (PHPUnit, `WebTestCase` for endpoints, in `services/symfony-api/tests/`): `docker compose exec api-php php bin/phpunit` → `OK (N tests, M assertions)`
  Database tests use `mago_test`. First time: `docker compose exec api-php php bin/console --env=test doctrine:database:create --if-not-exists`; after every new migration: `docker compose exec api-php php bin/console --env=test doctrine:migrations:migrate -n`
- Migrations: `docker compose exec api-php php bin/console doctrine:migrations:diff` (review the file), then `doctrine:migrations:migrate -n`; `doctrine:schema:validate` → `[OK]` for mapping and database
- PHPStan (level max, config in `services/symfony-api/phpstan.dist.neon`): `docker compose exec api-php vendor/bin/phpstan analyse --memory-limit=512M` → `[OK] No errors`.
  It reads the dev container from `var/cache/dev/`: if missing, run `docker compose exec api-php php bin/console cache:warmup` first
- Lint/format: `tools/php-cs-fixer/vendor/bin/php-cs-fixer check` (or `fix`), config in `.php-cs-fixer.dist.php`. A PostToolUse hook (`.claude/hooks/php-cs-fixer.sh`) formats every PHP file Claude edits. After cloning: `composer install -d tools/php-cs-fixer`
- Docker (from the repo root; first time: `cp .env.example .env` and set real values):
  - Start: `docker compose up -d --build`
  - Status: `docker compose ps` → `api-nginx`, `api-php`, `database` all `(healthy)`
  - Symfony console: `docker compose exec api-php php bin/console <command>` (e.g. `about` → `Version v8.1.11`, `Environment dev`)
  - Composer: `docker compose exec api-php composer <command>`
  - DB check from Symfony: `docker compose exec api-php php bin/console dbal:run-sql "SELECT version()"` → `PostgreSQL 16.x`
  - psql: `docker compose exec database sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'`
  - HTTP: `curl -s http://127.0.0.1:8080/nginx-health` → `ok`
  - Stop: `docker compose down` (add `-v` only to delete the database volume)
