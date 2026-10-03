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
   | `MAIL_MAILER` | `log` for demo (reset links land in logs) or `smtp` / Render SMTP |
   | `MAIL_HOST` | SMTP host (when not using `log`) |
   | `MAIL_PORT` | e.g. `587` |
   | `MAIL_USERNAME` | SMTP username |
   | `MAIL_PASSWORD` | SMTP password |
   | `MAIL_ENCRYPTION` | `tls` (or leave blank / use `MAIL_SCHEME`) |
   | `MAIL_FROM_ADDRESS` | e.g. `noreply@your-domain` |
   | `MAIL_FROM_NAME` | `BuildVault ERP` |

Forgot-password / reset uses Laravel Breeze tokens. With `MAIL_MAILER=log`, the reset URL is written to the app log (still a complete token flow). Disabled users cannot log in or request a reset.

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
| `boss@zhako.test` | `password` | Boss / Contractor |
| `accountant@zhako.test` | `password` | Accountant |
| `stock@zhako.test` | `password` | Stock Manager |

Test credentials only — rotate before any real company use.

## Empty books (wipe demo, keep logins)

Boot seeds **slim core** only (`SEED_DEMO=false` by default): roles, vault at zero, and the four demo login users. No projects, people, advances, or ledger rows.

**Preferred — no Shell (Shell is paid):** Super Admin → **Imports** → **Wipe to empty books**.

**One-shot on deploy (also no Shell):** set env `BUSINESS_WIPE_ON_BOOT=true`, let the service redeploy, confirm books are empty, then set `BUSINESS_WIPE_ON_BOOT=false` immediately (free-tier cold starts would wipe again if left on).

Optional CLI (Render Shell, costs money on some plans):

```bash
php artisan business:wipe --dry-run
php artisan business:wipe --commit
```

Re-deploy alone does **not** wipe production data unless `BUSINESS_WIPE_ON_BOOT` is true.

## Load Mayorca Excel (optional)

To replace empty (or leftover) books with the bundled workbook:

```bash
# Render Shell on the web service
php artisan mayorca:import --wipe --commit
```

Workbook path: `resources/imports/samples/hsabati-mayorca-zhako.xlsx` (copied into `storage/app/imports/samples/` automatically). **Not** run on every deploy — only when you intentionally refresh production data. Super Admin can trigger the same wipe+import from the dashboard button.

## Notes

- Free web services **sleep** after idle traffic; first request wakes them.
- Entrypoint runs `migrate --force` + `db:seed --force` every boot (seeders are idempotent).
- Set a **stable** `APP_KEY` in the dashboard so sessions/cookies survive restarts.
- SiteBunker docs remain in [DEPLOY.md](DEPLOY.md); Render is the public demo path.
