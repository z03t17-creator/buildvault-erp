# Operations — BuildVault ERP (daily / weekly)

Operational runbook for Super Admin and Accountant after deploy. See also [DEPLOY.md](DEPLOY.md), [COMPLIANCE.md](COMPLIANCE.md), [GO-LIVE.md](GO-LIVE.md).

## Daily

### Queue health

cPanel cron should drain the database queue every minute:

```bash
* * * * * cd /home/USER/path/to/app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

If jobs pile up, SSH and inspect:

```bash
cd /home/USER/path/to/app
php artisan queue:failed
php artisan queue:work --stop-when-empty -v
```

### FX health

1. Open **/dashboards/vault**.
2. Confirm Live FX shows a recent `fetched_at` and a sensible IQD/USD rate.
3. Prefer **Refresh FX** (API). Use **Override FX** only with a desk quote; overrides are written to the **Audit** log.

Fallback rate is **1310** when the API is unreachable (`ExchangeRateService`).

### Insurance holds

1. Open **/retention-holds** (or dashboard matured alerts).
2. Release matured holds so funds return to the staff payroll pool.
3. Scheduler also marks due holds: `php artisan retention:check-maturity` (via `schedule:run`).

### Audit spot-check

Accountants / Super Admins: **/audit** — filter by action (deposit, allocation, payout approve/reject, FX override), user, date.

## Backups

| Cadence | Command / UI | Location |
|---------|--------------|----------|
| Daily 02:00 | `backup:run-logged` via `schedule:run` | `storage/app/backups/` + `backups` table |
| On demand | **/backups** UI or `php artisan backup:run-logged` | same |
| Direct cron | `0 2 * * * cd /home/USER/path/to/app && php artisan backup:run-logged` | same |

Verify after each run: status `completed`, downloadable zip, size &gt; 0.

Lifecycle (hot → weekly archive → cold) is policy-only; see [COMPLIANCE.md](COMPLIANCE.md). Off-host copies of weekly/cold archives are an ops responsibility (not automated on SiteBunker).

### Restore dry-run (no production overwrite)

1. Download a completed backup zip from **/backups**.
2. On a **staging** copy of the app + empty DB, extract and restore the SQL dump with MariaDB tools.
3. Point a staging `.env` at that DB and confirm login + vault balances.
4. Do **not** restore over production without an explicit maintenance window and verified newer backup.

## Weekly

- Confirm cron entries still present in cPanel.
- Spot-check one project Excel export and one worker PDF.
- Confirm disk free space under `storage/app/backups/`.
- Review audit log for unexpected FX overrides or rejected payouts.

## Related URLs

| Path | Role |
|------|------|
| `/dashboards/vault` | Liquidity, FX, pools |
| `/dashboards/payroll` | Monthly payroll summary |
| `/payouts` | Approve / reject / reconcile |
| `/retention-holds` | Insurance maturity + release |
| `/backups` | Trigger / download |
| `/audit` | Sensitive-action trail |
