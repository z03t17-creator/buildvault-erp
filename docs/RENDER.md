# Deploy BuildVault ERP on Render (public `*.onrender.com`)

Same style as a simple Render web service (e.g. `https://judi-group.onrender.com/`): Docker web service + PostgreSQL.

## What this repo includes

| File | Role |
|------|------|
| `render.yaml` | Blueprint: free web service + Postgres |
| `Dockerfile` | Node Vite build + Composer + PHP 8.3 |
| `docker/render-entrypoint.sh` | migrate → seed → `artisan serve` on `$PORT` |

## One-time dashboard click-path (no API key required)

> **Important:** In Render Blueprint, search for the **repo name** `buildvault-erp` — do **not** search for the filename `render.yaml`.  
> If only `judi` appears, the Render GitHub App does not have access to `buildvault-erp` yet (see step 2).

1. **GitHub repo must contain code**  
   `https://github.com/z03t17-creator/buildvault-erp` currently exists but may be **empty**.  
   Push branch with Render files (Cursor tip includes `render.yaml`):
   ```bash
   git remote add github https://github.com/z03t17-creator/buildvault-erp.git
   git push -u github cursor/phase-1-1-laravel-scaffold-0471:main
   ```
   Confirm: `https://github.com/z03t17-creator/buildvault-erp/blob/main/render.yaml` is not 404.

2. **Grant Render access to that repo**  
   GitHub → Settings → Applications → **Render** → Configure → add **`buildvault-erp`** (or All repos).  
   Do **not** deploy the `judi` / judi-group repo.

3. Open [https://dashboard.render.com](https://dashboard.render.com) → **New** → **Blueprint** → select **`buildvault-erp`**.  
   **Or** manually: New PostgreSQL + New Web Service → Docker → same repo.

4. Name: `buildvault-erp` (URL becomes `https://buildvault-erp.onrender.com`).

5. Web service **Environment**:

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
