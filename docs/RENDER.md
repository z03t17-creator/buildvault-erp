# Deploy BuildVault ERP on Render (public `*.onrender.com`)

Same style as a simple Render web service (e.g. `https://judi-group.onrender.com/`): Docker web service + PostgreSQL.

## What this repo includes

| File | Role |
|------|------|
| `render.yaml` | Blueprint: free web service + Postgres |
| `Dockerfile` | Node Vite build + Composer + PHP 8.3 |
| `docker/render-entrypoint.sh` | migrate → seed → `artisan serve` on `$PORT` |

## One-time dashboard click-path (no API key required)

1. Push this branch to **GitHub** (Render cannot pull from Cursor Origin alone).  
   Suggested remote: `https://github.com/z03t17-creator/buildvault-erp`  
   Branch: `cursor/phase-1-1-laravel-scaffold-0471` or merge to `main`.
2. Open [https://dashboard.render.com](https://dashboard.render.com) → sign in with GitHub.
3. **New** → **Blueprint** → select `z03t17-creator/buildvault-erp` → apply `render.yaml`.  
   **Or** manually:
   - **New** → **PostgreSQL** (free/starter) → note **Internal Database URL**.
   - **New** → **Web Service** → Connect repo → **Docker** → branch with `Dockerfile`.
   - Name: `buildvault-erp` (URL becomes `https://buildvault-erp.onrender.com`).
4. Web service **Environment**:

   | Key | Value |
   |-----|--------|
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | `https://buildvault-erp.onrender.com` (or leave blank — entrypoint uses `RENDER_EXTERNAL_URL`) |
   | `APP_KEY` | Run locally: `php artisan key:generate --show` and paste (`base64:…`) |
   | `DB_CONNECTION` | `pgsql` |
   | `DATABASE_URL` | From Postgres (Render “Internal Database URL”) |
   | `DB_URL` | Same as `DATABASE_URL` |
   | `CACHE_STORE` | `database` |
   | `SESSION_DRIVER` | `database` |
   | `QUEUE_CONNECTION` | `database` |
   | `LOG_CHANNEL` | `stderr` |

5. Deploy → wait for build (npm + composer can take several minutes on free).  
6. Open `https://buildvault-erp.onrender.com/login` — free tier cold-starts may take ~30–60s.

## CLI deploy (if you have `RENDER_API_KEY`)

```bash
export RENDER_API_KEY=rnd_…
# https://render.com/docs/cli
render blueprints launch
# or create service via API / dashboard after push
```

This agent environment had **no** `RENDER_API_KEY`, so live deploy must be completed in the dashboard after GitHub is connected.

## Demo logins (seeded on boot)

| Email | Password | Role |
|-------|----------|------|
| `admin@zhako.test` | `password` | Super Admin |
| `accountant@zhako.test` | `password` | Accountant |
| `engineer@zhako.test` | `password` | Site Engineer |
| `worker@zhako.test` | `password` | Worker |

Test credentials only — rotate before any real company use.

## Notes

- Free web services **sleep** after idle traffic; first request wakes them.
- Entrypoint runs `migrate --force` + `db:seed --force` every boot (seeders are idempotent).
- Set a **stable** `APP_KEY` in the dashboard so sessions/cookies survive restarts.
- SiteBunker docs remain in [DEPLOY.md](DEPLOY.md); Render is the public demo path.
