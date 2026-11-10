# MaGo

Monorepo, learning project. Read `README.md` for the goal and principles.
Personal context (plan, status) lives outside the repo in `/mnt/c/LAVORO/### CLAUDE ###/` (STATUS.md): read it at the start, never write secrets there.

## Layout
- `services/symfony-api`: Symfony API (weeks 1-4), the only service that exists for now
- `docs/specs/`: one SPEC.md per feature, written by Bogdan; `_TEMPLATE.md` is the model

## Rules
- Work from the spec named in the task. If something is not in the spec, ask; do not invent scope.
- New concepts: use the Learning style and leave `TODO(human)` for real design decisions.
- Every change must end with a check that passes, shown with command + output (tests, PHPStan).
- Code, commits, comments and docs in English. Conventional commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).
- Never commit secrets; config via env vars. All local values live in the git-ignored root `.env`; its template is `.env.example` (dummy values). A new variable goes in `.env.example` and in the README table "Configuration and secrets". `DATABASE_URL` is built by `docker-compose.yml`, never written in a `.env` file.

## Commands
<!-- Keep them exact. Tests and PHPStan are filled in when they are installed. -->
- Tests: _tbd_
- PHPStan: _tbd_
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
