# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`AGENTS.md` holds the Laravel Boost rules (Pint after PHP edits, Pest, `make:` generators, no new deps/base folders without approval, docs only if requested). Follow it; this file only adds what it lacks.

## Project

AIdocs ("Rapi"): a web app that turns raw student content (pasted text, uploaded file, or prompt) into a structured JSON document, then builds a campus-formatted DOCX (+ PDF via Gotenberg). Spec in `docs/PRD.md` (Indonesian). **Current code is still the Laravel starter kit** (auth + settings only) plus the first document foundations; the AI pipeline from the PRD is not implemented yet.

## Commands

Run PHP tooling inside Docker so it uses Postgres (`docker compose up -d`; app on :8000, Gotenberg on :3002). Prefix every `php`/`composer`/`vendor/bin` command with `docker compose exec -T app`, e.g. `docker compose exec -T app php artisan test --compact`.

```bash
composer setup        # install, .env, key, migrate, npm build
composer dev          # artisan serve + queue:listen + vite (concurrently)
composer test         # config:clear, pint --test, phpstan, then artisan test
composer lint         # pint --parallel (fix); lint:check only verifies
composer types:check  # phpstan (larastan)
php artisan test --compact tests/Feature/SomeTest.php
php artisan test --compact --filter=testName
npm run build         # host, not Docker; needed if Vite manifest errors appear
```

Tests run on in-memory SQLite (`phpunit.xml`), not Postgres.

## Architecture

- Stack: Laravel 13, PHP 8.3+, Livewire 4, Flux UI (free tier only, per PRD), Fortify (auth, 2FA, passkeys), Tailwind via Vite, Pest, SQLite by default (Postgres in Docker). Sessions, cache and queue all use the `database` driver, so queue work needs `queue:listen` running.
- Pages are Livewire single-file components (`⚡*.blade.php`) under `resources/views/pages/**`, registered with the `pages::` namespace and routed via `Route::livewire('url', 'pages::settings.profile')` (see `routes/settings.php`). There are almost no controllers.
- Fortify logic lives in `app/Actions/Fortify/*` and is wired in `app/Providers/FortifyServiceProvider.php`; shared validation rules are traits in `app/Concerns/` (`PasswordValidationRules`, `ProfileValidationRules`) mixed into those actions.
- Layouts are `resources/views/layouts/{app,auth}/*` (sidebar/header, card/simple/split); shared bits in `resources/views/partials/`.
- Documents: `App\Models\Document` (soft deletes, `content` jsonb cast to array, `type`/`status` cast to enums in `app/Enums`) owned by a user and guarded by `DocumentPolicy` (owner only); `DocumentJob` tracks per-chapter AI jobs. Core logic lives in `app\Services\Documents` (not in Livewire components, so MCP can reuse it): `SchemaValidator` validates a document against `resources/schemas/document-v1.json` plus unique ids, and `TextNormalizer` turns raw pasted text into `p1..pn` paragraphs.
- `docs/PRD.md` defines the target design: UI red theme with light/dark mode, document states (`draft` → `ready` → `exported`), and a no-invented-content rule for paste/upload paths. Stitch screens are in `docs/design/{image,screen}`.

## Knowledge graph

`graphify-out/` contains a code graph (`graph.json`, `GRAPH_REPORT.md`, `graph.html`); a hook asks for `graphify query "<question>"` before raw grepping.
