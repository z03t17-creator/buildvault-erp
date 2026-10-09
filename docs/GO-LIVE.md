# Go-live runbook — BuildVault ERP (Zhako)

Use this with [DEPLOY.md](DEPLOY.md) and [OPERATIONS.md](OPERATIONS.md).  
**Do not mark production LIVE until SiteBunker SSH/domain steps below are completed by the operator.**

## Pre-flight (off-server)

- [ ] `npm ci && npm run build` — `public/build` committed or uploaded
- [ ] `php artisan test` green on release branch
- [ ] Production `.env` prepared from `.env.example` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`)
- [ ] MariaDB database + user created in cPanel
- [ ] Seeded `admin@zhako.test` password plan (rotate or replace before public use)
- [ ] Counsel confirmed retention target (see [COMPLIANCE.md](COMPLIANCE.md); default config **7** years)

## Host actions (SiteBunker / Zhiyar)

Replace placeholders:

| Placeholder | Meaning |
|-------------|---------|
| `USER` | cPanel username |
| `/home/USER/path/to/app` | App root (parent of `public/`) |
| `your-domain.example` | Live hostname |
| DB_* | cPanel MariaDB credentials |

1. Upload/pull release (include `public/build`; exclude `node_modules`, local `.env`).
2. Document root → `…/public`.
3. SSH:
   ```bash
   cd /home/USER/path/to/app
   composer install --no-dev --optimize-autoloader
   cp .env.example .env   # if first time — then edit
   php artisan key:generate
   php artisan migrate --force
   # First deploy only, after reviewing seeders:
   php artisan db:seed --force
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. cPanel → SSL/TLS → AutoSSL / free SSL → issue for domain; force HTTPS redirect.
5. Crons (cPanel → Cron Jobs), PHP 8.3 binary path as provided by host:
   ```bash
   * * * * * cd /home/USER/path/to/app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   * * * * * cd /home/USER/path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```
   Optional direct backup: `0 2 * * * cd /home/USER/path/to/app && php artisan backup:run-logged >> /dev/null 2>&1`
6. OPcache: enable in MultiPHP INI. LiteSpeed LSCache: cache `/public/build` static assets only (not authenticated HTML).
7. Verify: login → `/dashboards/vault` FX → create/approve test payout on staging data → `/backups` trigger → `/audit` shows rows.

## Post-launch

- [ ] Rotate admin credentials
- [ ] Confirm first nightly backup completed
- [ ] Watch queue failures for 24h
- [ ] Note Phase **3.7 GAP** (spending limits / uncleared float) for a follow-up release

## Status meanings

| Status | Meaning |
|--------|---------|
| **LIVE** | App answering on production HTTPS with migrate/cron/SSL done |
| **BLOCKED** | Code/docs ready; host credentials or operator steps still required |
