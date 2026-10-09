# Local installation — BuildVault ERP

This guide sets up BuildVault ERP for local development (Laravel 11 + Breeze Inertia React + Tailwind). For SiteBunker production, see [DEPLOY.md](DEPLOY.md).

## Prerequisites

| Tool | Version |
|------|---------|
| PHP | 8.3+ |
| Composer | 2.x |
| Node.js | 20+ (LTS recommended) |
| npm | 10+ |
| Database | SQLite (simplest) or MariaDB/MySQL |

PHP extensions typically required: `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `pdo_sqlite` and/or `pdo_mysql`.

## Steps

### 1. Clone and install PHP dependencies

```bash
git clone <repository-url> buildvault-erp
cd buildvault-erp
composer install
```

### 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` defaults to SiteBunker-friendly drivers even for local use:

```env
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

(SQLite works with these drivers after migrate.)

### 3. Database

**SQLite (default for quick local use):**

```bash
touch database/database.sqlite
```

Ensure `.env` contains:

```env
DB_CONNECTION=sqlite
# DB_DATABASE is optional; Laravel defaults to database/database.sqlite
```

**MariaDB / MySQL:**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=buildvault
DB_USERNAME=root
DB_PASSWORD=
```

Then create the database, migrate, and seed:

```bash
php artisan migrate
php artisan db:seed
```

Or in one step:

```bash
php artisan migrate:fresh --seed
```

**Local test Super Admin** (dev only — not a production secret):

| Field | Value |
|-------|--------|
| Email | `admin@zhako.test` |
| Password | `password` |
| Role | Super Admin |

Additional Phase 2 demo logins (same password `password`): `boss@zhako.test` (Boss / Contractor), `accountant@zhako.test` (Accountant), `stock@zhako.test` (Stock Manager).

This also seeds the central **Zhako** vault with zero balances (demo insurance seed may add sample deposits afterward).

### 4. Public storage link (worker avatars)

Worker photos are stored on the **public** disk under `storage/app/public/uploads/workers/` and served at `/storage/uploads/workers/...`. Create the symlink once after clone:

```bash
php artisan storage:link
```

This links `public/storage` → `storage/app/public`. Without it, avatar URLs 404 in the browser.

### 5. Frontend dependencies and build

```bash
npm install
npm run build
```

For hot reload during UI work:

```bash
npm run dev
```

### 6. Run the app

```bash
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). Breeze auth routes (register/login) are available out of the box.

## Useful commands

```bash
php artisan --version    # confirm Laravel boots
php artisan migrate:fresh --seed
php artisan storage:link # worker avatar uploads
php artisan route:list
npm run build            # production Vite build → public/build
```

## Progressive Web App (install)

1. Run `npm run build` so Vite assets exist under `public/build`.
2. Serve over **HTTPS** (production) or `php artisan serve` / localhost (dev SW registration works on localhost).
3. Open the app → browser menu → **Install BuildVault ERP** / **Add to Home Screen**.
4. Manifest: `/manifest.json` · Service worker: `/sw.js` · Offline shell: `/offline.html` · Icons: `/icons/*` (from ZHAKO logo).

The service worker caches the offline shell and static assets only; authenticated Inertia navigations still require a network.

## Notes

- Do not commit `.env` or `vendor/` / `node_modules/`.
- `public/build` is produced by `npm run build` and is gitignored in the scaffold; regenerate it after frontend changes.
- Seeded credentials (`admin@zhako.test` / `password`) are for local development only.
- Uploaded avatars live under `storage/app/public/uploads/workers/` (gitignored); ensure `storage:link` is run on each environment.
