# BuildVault ERP

BuildVault ERP (Zhako SiteLedger) is the construction operations and vault ledger system for **Zhako Construction Company**. It manages site finances, staff payroll, insurance reserves, penalties, and ability-to-pay checks around a shared Zhako vault.

## Stack (Phase 1.1)

- **Laravel 11** (PHP 8.3)
- **Laravel Breeze** with **Inertia.js + React**
- **Tailwind CSS** + Vite

Later phases add Spatie roles, multilingual UI (EN / کوردی / العربية), vault pools, and SiteBunker production hardening. This repository currently contains the Phase 1.1 scaffold only.

## Requirements

- PHP 8.3+ with common extensions (`mbstring`, `xml`, `curl`, `zip`, `sqlite3` or `mysql`, `bcmath`, `intl`)
- Composer 2
- Node.js 20+ and npm (for local asset builds only)
- SQLite (local default) or MariaDB/MySQL

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if using SQLite
php artisan migrate
npm install
npm run build
php artisan serve
```

For a fuller local setup walkthrough, see [docs/INSTALL.md](docs/INSTALL.md).

For SiteBunker Enterprise (cPanel/SSH) deployment notes, see [docs/DEPLOY.md](docs/DEPLOY.md).

## Development

```bash
# Terminal 1 — PHP
php artisan serve

# Terminal 2 — Vite HMR
npm run dev
```

## License

Proprietary — Zhako Construction Company.
