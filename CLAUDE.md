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
- Never commit secrets; config via env vars.

## Commands
<!-- Filled in when services/symfony-api is created (week 1, day 1). Keep them exact. -->
- Tests: _tbd_
- PHPStan: _tbd_
- Lint/format: `tools/php-cs-fixer/vendor/bin/php-cs-fixer check` (or `fix`), config in `.php-cs-fixer.dist.php`. A PostToolUse hook (`.claude/hooks/php-cs-fixer.sh`) formats every PHP file Claude edits. After cloning: `composer install -d tools/php-cs-fixer`
- Docker: _tbd_
