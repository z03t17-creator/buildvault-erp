# Import template validation rules

Phase 4.5 templates · Phase 4.6 enforces these rules on upload.

## Workers (`workers`)

Headers: `name`, `project_name`, `role`, `daily_rate_usd`, `overtime_rate_usd`, `spending_limit_usd`, `phone`, `national_id_number`

| Column | Rule |
|---|---|
| `name` | `required|string|max:255` |
| `project_name` | `nullable|string|exists:projects,name` |
| `role` | `nullable|in:engineer,supervisor,subcontractor,laborer` |
| `daily_rate_usd` | `nullable|numeric|min:0` |
| `overtime_rate_usd` | `nullable|numeric|min:0` |
| `spending_limit_usd` | `nullable|numeric|min:0` |
| `phone` | `nullable|string|max:64` |
| `national_id_number` | `nullable|string|max:64` |

- project_name must match an existing project (or leave blank for unassigned).
- role defaults to laborer when empty.

## Projects (`projects`)

Headers: `name`, `location`, `status`, `total_budget_usd`, `start_date`, `end_date`, `description`

| Column | Rule |
|---|---|
| `name` | `required|string|max:255|unique:projects,name` |
| `location` | `nullable|string|max:255` |
| `status` | `nullable|in:planning,active,on_hold,completed,archived` |
| `total_budget_usd` | `nullable|numeric|min:0` |
| `start_date` | `nullable|date` |
| `end_date` | `nullable|date|after_or_equal:start_date` |
| `description` | `nullable|string` |

- status defaults to planning when empty.
- Dates use YYYY-MM-DD.

## Attendances (`attendances`)

Headers: `worker_name`, `date`, `check_in`, `check_out`, `status`, `late_minutes`, `overtime_hours`, `floor_name`

| Column | Rule |
|---|---|
| `worker_name` | `required|string|exists:workers,name` |
| `date` | `required|date` |
| `check_in` | `nullable|date_format:H:i` |
| `check_out` | `nullable|date_format:H:i|after:check_in` |
| `status` | `nullable|in:present,late,absent_unexcused,leave_paid,leave_sick` |
| `late_minutes` | `nullable|integer|min:0` |
| `overtime_hours` | `nullable|numeric|min:0` |
| `floor_name` | `nullable|string|exists:floors,name` |

- worker_name must match an existing worker.
- Duplicate worker+date rows are rejected.

## Payouts (`payouts`)

Headers: `project_name`, `category`, `amount_usd`, `worker_name`, `retention_holdback`, `notes`

| Column | Rule |
|---|---|
| `project_name` | `required|string|exists:projects,name` |
| `category` | `required|in:expenses,payroll,retention,penalty,profit` |
| `amount_usd` | `required|numeric|gt:0` |
| `worker_name` | `nullable|string|exists:workers,name` |
| `retention_holdback` | `nullable|numeric|min:0` |
| `notes` | `nullable|string|max:2000` |

- Creates pending payouts only; approve/reconcile stays in the UI/services.
- Liquidity checks run during import processing.
- Empty retention_holdback uses default insurance % for payroll+worker.

## Modes

- **partial** — import valid rows; skip/report invalid.
- **atomic** — all-or-nothing; no rows imported if any row is invalid.
