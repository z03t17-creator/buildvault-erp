# BuildVault ERP

BuildVault ERP (Zhako SiteLedger) is the construction operations and vault ledger system for **Zhako Construction Company**. It manages site finances, staff payroll, insurance reserves, penalties, and ability-to-pay checks around a shared Zhako vault.

## Stack

- **Laravel 11** (PHP 8.3)
- **Laravel Breeze** with **Inertia.js + React** + **Tailwind CSS**
- **Spatie Laravel Permission** (Super Admin, Accountant, Site Engineer, Worker)
- Locales: English / کوردی / العربية (RTL for ckb/ar)
- Host target: [SiteBunker Enterprise](https://sitebunker.net/web-ssd-hosting/) (cPanel, MariaDB, SSH)

## Requirements

| Tool | Notes |
|------|--------|
| PHP 8.3+ | Extensions: `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `pdo_mysql` / `pdo_sqlite` |
| Composer 2 | PHP dependencies |
| Node.js 20+ | **Local/CI only** — build Vite assets; not available on SiteBunker |
| MariaDB or SQLite | Production: MariaDB; local: SQLite OK |

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if using SQLite
php artisan migrate --seed
php artisan storage:link   # public avatars → storage/app/public/uploads/workers
npm install
npm run build
php artisan serve
```

- Login: `admin@zhako.test` / `password` (dev Super Admin — see [docs/INSTALL.md](docs/INSTALL.md))
- Full local setup: [docs/INSTALL.md](docs/INSTALL.md)
- SiteBunker cPanel/SSH deploy: [docs/DEPLOY.md](docs/DEPLOY.md)

## SiteBunker notes (production)

- **No Node on the host** (NodeJS Selector unavailable) — run `npm ci && npm run build` off-server and deploy `public/build`.
- PHP **8.3**, MariaDB, document root → `public/`.
- Cache / queue / session drivers: **database** (see `.env.example`).
- Queue via cPanel cron: `php artisan queue:work --stop-when-empty`.
- Scheduler cron (every minute): `php artisan schedule:run` — runs daily `backup:run-logged` at 02:00 into `storage/app/backups/`.
- Direct backup: `php artisan backup:run` (Spatie) or `php artisan backup:run-logged` (logs to `backups` table / UI).

## Progressive Web App (PWA)

BuildVault can be installed as a standalone app (Chrome / Edge / Android / iOS Safari “Add to Home Screen”).

| File | Role |
|------|------|
| `public/manifest.json` | Name **BuildVault ERP**, slate/emerald theme, icons, `display: standalone` |
| `public/sw.js` | Minimal service worker — caches static assets + offline shell |
| `public/offline.html` | Offline fallback page |
| `public/icons/` | Icons generated from `public/images/zhako-logo.jpg` |

After `npm run build` and deploy, open the site over **HTTPS** (or localhost), then use the browser’s **Install app** / **Add to Home Screen**. SiteBunker SSL is enough; no Node on the host for the SW/manifest (they live under `public/`).

## Development

```bash
php artisan serve   # Terminal 1
npm run dev         # Terminal 2 — Vite HMR
```

## License

Proprietary — Zhako Construction Company.
