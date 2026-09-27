# Deployment — SiteBunker Enterprise

BuildVault ERP deploys to [SiteBunker Enterprise](https://sitebunker.net/web-ssd-hosting/): cPanel, LiteSpeed, SSH, free SSL, MariaDB, PHP 8.3, OPcache (~40GB NVMe, 3 vCPU, 3GB RAM).

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

## Production `.env` drivers

Copy from `.env.example`. Keep these for SiteBunker (no Redis required):

```env
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
```

Laravel 11 uses `CACHE_STORE` (not the older `CACHE_DRIVER`). Migrations must include the framework `cache`, `jobs`, and `sessions` tables (shipped in this app).

## Deploy steps (cPanel / SSH)

1. **Build assets off-server**  
   ```bash
   npm ci && npm run build
   ```
   Keep `public/build` (manifest + hashed assets) in the release you upload.

2. **Upload / pull code**  
   Git over SSH, rsync, or File Manager. Exclude `node_modules` and local `.env`. Prefer running Composer **on the host**:
   ```bash
   cd /home/USER/path/to/app
   composer install --no-dev --optimize-autoloader
   ```

3. **Document root**  
   Point the domain/subdomain to `.../public`.

4. **Configure `.env`**  
   As above; `php artisan key:generate` once if needed.

5. **Migrate + seed (first deploy)**  
   ```bash
   php artisan migrate --force
   php artisan db:seed --force   # roles, Zhako vault, optional admin — review before production
   ```
   Change or remove the seeded `admin@zhako.test` user before go-live.

6. **Optimize**  
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link
   ```

7. **cPanel cron — queue + scheduler**  
   No supervisor/long-running worker. Run often:
   ```bash
   * * * * * cd /home/USER/path/to/app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   * * * * * cd /home/USER/path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```
   The scheduler runs insurance maturity checks and **daily backups** at 02:00 (`backup:run-logged` → `storage/app/backups/`).

8. **Backups (Phase 4.8)**  
   - Package: `spatie/laravel-backup`  
   - Disk: `backups` → `storage/app/backups/` (local; SiteBunker-friendly)  
   - Includes: DB dump + `storage/app/uploads` + `.env` (not full `vendor`/codebase)  
   - UI: **/backups** — list, trigger, download  
   - Direct cron alternative (no Laravel scheduler):
     ```bash
     0 2 * * * cd /home/USER/path/to/app && php artisan backup:run >> /dev/null 2>&1
     ```
     Prefer `backup:run-logged` if you want rows in the `backups` table.

9. **SSL**  
   Enable free SSL in cPanel; set `APP_URL` to `https://…`.

## Go-live checklist

- [ ] PHP 8.3 for the domain
- [ ] MariaDB credentials in `.env`
- [ ] `public/build` present (built off-server)
- [ ] Document root → `public/`
- [ ] `APP_DEBUG=false`
- [ ] `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` = `database`
- [ ] Cron for `queue:work --stop-when-empty`
- [ ] Cron for `schedule:run` (daily backup + maturity)
- [ ] `storage/` and `bootstrap/cache/` writable (`storage/app/backups/` writable)
- [ ] Dev seed password rotated or admin recreated

## Related

- Local setup: [INSTALL.md](INSTALL.md)
- Defaults in repo: `.env.example`
