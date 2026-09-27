# Compliance — audit trail & data retention

**Not legal advice.** Retention periods for Iraqi commercial and tax records are commonly discussed as **7+ years**; confirm the exact obligation for Zhako Construction Company with qualified counsel before relying on this document.

## Audit trail

BuildVault records sensitive financial actions via `spatie/laravel-activitylog` (`activity_log` table, log name `audit`):

| Event | When |
|-------|------|
| `vault.deposit` | Vault deposit + pool split |
| `allocation.changed` | Project allocation pools credited/debited |
| `payout.approved` | Payout approved |
| `payout.rejected` | Payout rejected |
| `fx.rate_overridden` | Manual FX override |

UI: **/audit** (Super Admin, Accountant). Filter by action, user, date.

Activity log cleanup default (package): `config/activitylog.php` → `delete_records_older_than_days` (365). For multi-year compliance, **export or archive** audit rows before pruning, or raise that value to match `COMPLIANCE_RETENTION_YEARS`.

## Export formats (business records)

| Export | Format | Route / UI |
|--------|--------|------------|
| Project pack | Excel (`.xlsx`) | `/exports` → project |
| Worker payroll period | PDF | `/exports` → worker |
| Payout voucher | PDF | `/exports` → voucher |
| Documents | Original uploads on `uploads` disk | `/documents` |

Retain exported copies outside the app if required by counsel (email archive, accounting drive, etc.).

## Config flag

```env
# Operational target — confirm with counsel (often 7+ years in Iraq)
COMPLIANCE_RETENTION_YEARS=7
COMPLIANCE_BACKUP_DAILY_HOT_DAYS=30
COMPLIANCE_BACKUP_WEEKLY_ARCHIVE_WEEKS=52
COMPLIANCE_BACKUP_COLD_YEARS=7
```

See `config/compliance.php`.

## Backup lifecycle (operational policy)

Documented policy — **not** fully automated on SiteBunker:

1. **Daily hot** — Spatie `backup:run-logged` → `storage/app/backups/` for ~30 days (prune older zips manually or by cron when disk is tight).
2. **Weekly archive** — Copy one successful weekly zip off-host (cPanel backup, S3, NAS, encrypted drive) and keep ~52 weeks.
3. **Long-term cold** — Annual or project-close archives retained for `COMPLIANCE_BACKUP_COLD_YEARS` (default 7), offline or immutable storage.

App backups include DB dump + `storage/app/uploads` + `.env` (see Spatie backup config). They do **not** replace full server/cPanel backups.

## Related

- [DEPLOY.md](DEPLOY.md) — cron and SSL
- [OPERATIONS.md](OPERATIONS.md) — daily backup verification
- [GO-LIVE.md](GO-LIVE.md) — launch checklist
