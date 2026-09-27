# Deployment — SiteBunker Enterprise

BuildVault ERP deploys to [SiteBunker Enterprise](https://sitebunker.net/web-ssd-hosting/): cPanel, LiteSpeed, SSH, free SSL, MariaDB, PHP 8.3, OPcache (~40GB NVMe, 3 vCPU, 3GB RAM).

Step-by-step launch: [GO-LIVE.md](GO-LIVE.md) · Daily ops: [OPERATIONS.md](OPERATIONS.md) · Retention: [COMPLIANCE.md](COMPLIANCE.md).

## Critical: no Node on the host

**SiteBunker NodeJS Selector is unavailable.** Do not run `npm`, Vite, or Inertia SSR on the server.

| Where | What |
|-------|------|
| Local / CI | `npm ci && npm run build` → produces `public/build` |
| Host | PHP 8.3 + Composer only; ship prebuilt `public/build` |

## Runtime requirements

| Item | Value |
|------|--------|
| PHP | **8.3** (cPanel MultiPHP) |
| Database | **MariaDB** (cPanel → MySQL Databases) |
| Document root | App `public/` directory |
| Composer (SSH) | `composer install --no-dev --optimize-autoloader` |
| Node | **Not on host** |

## Production `.env` (required)

```env
APP_NAME="BuildVault ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_cpanel_db
DB_USERNAME=your_cpanel_user
DB_PASSWORD=your_db_password

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# Optional compliance targets (not legal advice)
COMPLIANCE_RETENTION_YEARS=7
```

Laravel 11 uses `CACHE_STORE` (not the older `CACHE_DRIVER`).

## OPcache (SiteBunker / cPanel)

In **MultiPHP INI Editor** for the domain (PHP 8.3):

- `opcache.enable=1`
- `opcache.memory_consumption=128` (or host default ≥64)
- `opcache.validate_timestamps=0` in production after each deploy (or leave `1` with short `revalidate_freq` if you prefer simpler deploys)
- Restart PHP/LiteSpeed if the panel requires it after INI changes

## LiteSpeed LSCache (static assets only)

Cache **`/public/build/*`** (Vite hashed JS/CSS) as public static files. Do **not** full-page cache authenticated Inertia HTML (CSRF/session). Exclude `/login`, `/dashboard`, and other app routes from page cache. If LSCache is unused, LiteSpeed still serves static files efficiently from `public/build`.

## Force HTTPS

1. cPanel → **SSL/TLS Status** → Run **AutoSSL** / free SSL for the domain and aliases.
2. Confirm green lock; fix DNS/CAA if AutoSSL fails.
3. Enable **Force HTTPS Redirect** (cPanel Domains or `.htaccess` in `public/`).
4. Set `APP_URL=https://your-domain.example` (no trailing slash issues; match the cert CN/SAN).
5. App trusts proxies (`bootstrap/app.php`) so `URL::forceScheme` / `$request->secure()` work behind LiteSpeed SSL termination.
6. Security headers middleware sets `Strict-Transport-Security` when the request is secure or `APP_ENV=production`.

CSRF remains Laravel’s default. CORS is **not** enabled app-wide (same-origin Inertia).

## Deploy steps (cPanel / SSH)

1. **Build assets off-server**  
   ```bash
   npm ci && npm run build
   ```
   Keep `public/build` in the release you upload.

2. **Upload / pull code**  
   ```bash
   cd /home/USER/path/to/app
   # git pull, or upload release tarball
   composer install --no-dev --optimize-autoloader
   ```

3. **Document root** → `.../public`.

4. **Configure `.env`** as above; `php artisan key:generate` once if needed.

5. **Migrate + seed (first deploy)**  
   ```bash
   php artisan migrate --force
   php artisan db:seed --force   # review before production
   ```
   Change or remove seeded `admin@zhako.test` before go-live.

6. **Optimize**  
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link
   ```

7. **cPanel cron examples** (replace path; use host PHP 8.3 binary if `php` is wrong version):

   ```bash
   # Queue drain
   * * * * * cd /home/USER/path/to/app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1

   # Scheduler (retention maturity + daily backup:run-logged at 02:00)
   * * * * * cd /home/USER/path/to/app && php artisan schedule:run >> /dev/null 2>&1

   # Optional direct daily backup (if not using scheduler)
   0 2 * * * cd /home/USER/path/to/app && php artisan backup:run-logged >> /dev/null 2>&1
   ```

8. **Backups** — UI **/backups**; disk `storage/app/backups/`. Prefer `backup:run-logged` so rows appear in the DB/UI.

9. **SSL verify** — AutoSSL issued; `https://` loads login; mixed-content free; HSTS header present on HTTPS responses.

## Go-live checklist

- [ ] PHP 8.3 for the domain + OPcache on
- [ ] MariaDB credentials in `.env`
- [ ] `public/build` present (built off-server)
- [ ] Document root → `public/`
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
- [ ] `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` = `database`
- [ ] Cron for `queue:work --stop-when-empty`
- [ ] Cron for `schedule:run` (daily backup + maturity)
- [ ] AutoSSL + force HTTPS
- [ ] `storage/` and `bootstrap/cache/` writable
- [ ] Dev seed password rotated
- [ ] First backup verified from **/backups**

## Related

- Local setup: [INSTALL.md](INSTALL.md)
- Operations: [OPERATIONS.md](OPERATIONS.md)
- Go-live: [GO-LIVE.md](GO-LIVE.md)
- Compliance: [COMPLIANCE.md](COMPLIANCE.md)
- Part 11: [PART11-CHECKLIST.md](PART11-CHECKLIST.md)
- Defaults: `.env.example`
