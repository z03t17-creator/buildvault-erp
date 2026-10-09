# Part 11 deliverables checklist (Phase 5.5)

Blueprint Part 11 walk for BuildVault ERP (Zhako). Status as of Phase 5.

| # | Deliverable | Status | Notes |
|---|-------------|--------|-------|
| 1 | Schema (projects → towers → floors, workers, attendance, vault ledger, payouts, penalties, retention, documents, imports, backups, activity_log) | **PASS** | Migrations through Phase 5.2 |
| 2 | Eloquent models + relationships | **PASS** | Core domain models present |
| 3 | ExchangeRateService (API + fallback 1310 + cache + override) | **PASS** | Override audited |
| 4 | Localization EN / کوردی / العربية + RTL | **PASS** | Locale middleware + switcher |
| 5 | Spatie RBAC + policies | **PASS** | Phase 5.1 |
| 6 | Import / export / backup | **PASS** | Phases 4.5–4.8 |
| 7 | File management (avatars, documents gallery) | **PASS** | Phases 2.7 / 4.4 |
| 8 | Vault + payroll dashboards | **PASS** | Phase 4.1–4.2 |
| 9 | PWA (manifest + SW) | **PASS** | Phase 4.9 |
| 10 | Seeders (roles, vault, admin) | **PASS** | Phase 1.x |
| 11 | Hosting tuning docs (SiteBunker, cron, OPcache, LSCache, SSL) | **PASS** | Phases 5.3–5.6 |
| 12 | Audit logging UI | **PASS** | Phase 5.2 |
| 13 | Compliance / retention docs | **PASS** | Phase 5.7 |
| — | **Phase 3.7** uncleared float / spending-limit enforcement | **GAP** | `workers.spending_limit_usd` stored; no liquidity/payout enforcement or uncleared-float workflow |

## Known gap (honest)

**Phase 3.7** was skipped at the Phase 3.8 checkpoint: reconciliation of uncleared float and hard spending-limit checks are **not** implemented beyond the worker field stub. Payout reconcile (status + penalties) **is** implemented and is separate from 3.7.

## Related

- Internal store: `phase-5-5-checklist.md`
- Deploy: [DEPLOY.md](DEPLOY.md) · Ops: [OPERATIONS.md](OPERATIONS.md)

## Final testing (Phase 5.8)

See agent store `internal/phase-5-8-final-testing.md` — suite green; PAUSE before SiteBunker go-live.
