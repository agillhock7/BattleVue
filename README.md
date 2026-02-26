# BattleVue (Mode 1 Async PvP, MySQL)

BattleVue is a Vue 3 + Vite + TypeScript PWA with a PHP + MySQL backend for deterministic async PvP bot battles.

## Repository Tree

```text
/
  frontend/
    index.html
    package.json
    vite.config.ts
    src/
      App.vue
      main.ts
      router/
      stores/
      services/
      components/
      views/
  backend/
    public/
      index.php
    src/
      Config.php
      Db.php
      Response.php
      AuthService.php
      Csrf.php
      RateLimiter.php
      Repos/*
      Services/*
      Validators/*
      Simulator/SimulatorV1.php
    migrations/
      0001_init.sql
      0002_seed_core_content.sql
    scripts/
      migrate.php
      seed.php
      pack.php
    config/
      config.example.php
    .htaccess
  dist/
    index.html
    assets/
  api/
    (packaged deploy mirror of backend public/src/scripts/migrations/config template)
  .cpanel.yml
  .env.example
  package.json
  postman_collection.json
```

## Stack

- Frontend: Vue 3, Vite, TypeScript, Pinia, Vue Router, Vite PWA plugin
- Backend: PHP 8.x, PDO MySQL, cookie sessions, CSRF double-submit tokens
- Data: MySQL 8.x migrations + seed

## Local Setup

1. Copy backend config:
   - `cp backend/config/config.example.php backend/config/config.php`
2. Edit DB and secrets in `backend/config/config.php`.
3. Run migrations:
   - `php backend/scripts/migrate.php`
4. Seed base data:
   - `php backend/scripts/seed.php`
5. Install frontend dependencies:
   - `npm --prefix frontend install`
6. Run frontend dev server:
   - `npm --prefix frontend run dev`

## Build + Package (for cPanel deploy)

1. Build frontend into repo-root `dist/`:
   - `npm --prefix frontend run build`
2. Package backend into top-level `api/`:
   - `php backend/scripts/pack.php`
3. Commit deploy artifacts:
   - `git add dist api`
   - `git commit -m "Build frontend and package api"`
   - `git push`

Or use root scripts:

- `npm run build:frontend`
- `npm run pack:api`
- `npm run package:deploy`

## cPanel Deployment

Target:

- Document root: `/home/gopsapp1/battlevue.gops.app`
- API path: `/home/gopsapp1/battlevue.gops.app/api`

`.cpanel.yml` behavior:

- Syncs `dist/` to docroot root (`index.html`, `assets/`, manifest, etc.)
- Syncs `api/` to `/api`
- Preserves root `.htaccess`
- Does not run `npm install`/`npm build` on server

## Backend Security Implemented

- Session auth via DB-backed session tokens (`sessions` table)
- Cookie flags: HttpOnly session cookie, SameSite=Lax, Secure in prod
- CSRF protection on authenticated POST routes (double-submit token)
- Same-origin enforcement for CORS
- Input validation for bot blueprints/rulesets
- Rules DSL allowlists and hard limits
- File-backed rate limiter for auth + chat
- API deny rules for `/api/scripts` and `/api/config` in `.htaccess`

## API Endpoints

Implemented modules:

- Auth: register/login/logout/me
- Social: user search, friend request/respond/list/remove, blocks add
- Quests: tracks, quests, quest detail, submit-step, complete
- Inventory/Bots: inventory, blueprints create/update/list, rulesets create/update/list, validators
- Matches: queue, challenge, submit, simulate (protected internal key), history, replay
- Match chat polling: get/post messages
- Notifications: list, read

## Deterministic Simulation

- `backend/src/Simulator/SimulatorV1.php`
- Tick loop max 200 ticks
- Seeded xorshift32 PRNG
- Rules evaluated top-down by priority
- Speed-based action order
- Event log stored in `match_events`
- `simulator_version` stored per match

## cURL Smoke Tests

Use a cookie jar to preserve session + CSRF cookie:

```bash
# Register
curl -i -c cookies.txt -X POST https://battlevue.gops.app/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"username":"demo3","email":"demo3@example.com","password":"Pass12345!"}'

# Login
curl -i -c cookies.txt -b cookies.txt -X POST https://battlevue.gops.app/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"identity":"demo3","password":"Pass12345!"}'

# Read CSRF token from cookie jar and queue a match
CSRF=$(awk '/battlevue_csrf/ {print $7}' cookies.txt)
curl -i -c cookies.txt -b cookies.txt -X POST https://battlevue.gops.app/api/matches/queue \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-Token: ${CSRF}" \
  -d '{"mode":"casual"}'

# Fetch replay for match 1
curl -i -b cookies.txt https://battlevue.gops.app/api/matches/1/replay
```

## Notes

- `backend/config/config.php` is gitignored.
- `dist/` and `api/` are intentionally committed for cPanel Git deploy.
- Import `postman_collection.json` for quick API testing.
