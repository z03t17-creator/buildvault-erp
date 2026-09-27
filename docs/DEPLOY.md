# Deployment — SiteBunker Enterprise

BuildVault ERP targets [SiteBunker Enterprise](https://sitebunker.net/web-ssd-hosting/) shared hosting: cPanel, LiteSpeed, SSH, free SSL, MariaDB, PHP 8.3, OPcache.

This document is an overview for Phase 1.1. Host-specific `.env` defaults for cache/queue/session are refined in a later phase; use the settings below when preparing production.

## Important: no Node on the host

**SiteBunker NodeJS Selector is unavailable** on this plan. You cannot run `npm install`, `npm run build`, or Inertia SSR on the server.

Always:

1. Build frontend assets on your workstation or CI (`npm ci && npm run build`).
2. Deploy the resulting `public/build` directory (manifest + hashed assets) with the PHP application.
3. Do not rely on Vite HMR or `npm run dev` in production.

## Runtime requirements

| Item | Value |
|------|--------|
| PHP | **8.3** (select in cPanel MultiPHP) |
| Database | **MariaDB** (create via cPanel → MySQL Databases) |
| Web root | Point the domain/subdomain document root to the app’s `public/` directory |
| Composer | Run via SSH: `composer install --no-dev --optimize-autoloader` |
| Node | **Not used on the host** — build off-server |

## Recommended `.env` drivers (SiteBunker)

Use database-backed drivers so Redis/Memcached are not required:

```env
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Ensure the Laravel cache, jobs, and sessions migrations have been run so the required tables exist.

## Deploy outline (cPanel / SSH)

1. **Build locally (or in CI)**  
   `composer install --no-dev` (optional locally) + `npm ci && npm run build`. Keep `public/build` for upload.

2. **Upload application**  
   Sync the project to the hosting account (Git over SSH, rsync, or cPanel File Manager). Exclude `node_modules`, local `.env`, and development-only files. Include `vendor` (from `composer install --no-dev` on SSH) or run Composer on the server after upload.

3. **Document root**  
   Set the site’s document root to `.../public` so `index.php` is the front controller.

4. **Production `.env`**  
   Copy `.env.example`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, MariaDB credentials, and the database cache/queue/session drivers above. Run `php artisan key:generate` once if no key exists.

5. **Migrate**  
   `php artisan migrate --force`

6. **Optimize**  
   `php artisan config:cache`  
   `php artisan route:cache`  
   `php artisan view:cache`

7. **Queue worker via cron**  
   SiteBunker has no long-running supervisor process. Add a cPanel cron entry that runs frequently, for example:

   ```bash
   * * * * * cd /home/USER/path/to/app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```

   Also schedule Laravel’s scheduler if needed:

   ```bash
   * * * * * cd /home/USER/path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```

8. **SSL**  
   Enable free SSL in cPanel and force HTTPS for `APP_URL`.

## Checklist before go-live

- [ ] PHP 8.3 selected for the domain
- [ ] MariaDB database and user created; `.env` credentials verified
- [ ] `public/build` present and current (built off-server)
- [ ] Document root → `public/`
- [ ] `APP_DEBUG=false`
- [ ] Cache / queue / session = `database`
- [ ] Cron for `queue:work --stop-when-empty` (and `schedule:run` if used)
- [ ] Storage and bootstrap cache directories writable by the web user

## Out of scope for Phase 1.1

Vault migrations, Spatie permissions, exchange-rate services, and custom locale middleware ship in later phases. This file documents the hosting model so asset and runtime choices stay compatible from day one.
