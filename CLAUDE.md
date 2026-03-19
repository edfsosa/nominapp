# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development (server + queue + vite)
composer run dev

# Run all tests
composer run test

# Run a single test
php artisan test --filter=TestName
php artisan pest tests/Feature/SomeTest.php

# Build frontend
npm run build

# Database
php artisan migrate
php artisan db:seed --class=ProductionSeeder   # new client setup (sets admin user)
```

## Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Admin panel:** Filament 3.3 (all CRUD at `/admin`)
- **Frontend:** Blade + Vite + Tailwind CSS v4 + Vue (only for attendance/facial pages)
- **Testing:** Pest
- **Queue/Cache/Session:** database driver
- **Locale:** Spanish (`es`), timezone `America/Asuncion`, currency Guaraní (Gs.)

## Architecture

### Hierarchy
Two separate axes that converge in `Contract`:
- **Physical/location:** `Company → Branch → Employee`
- **Organizational:** `Company → Department → Position` (`departments.company_id` FK — each company defines its own departments)

`Employee` has `branch_id` (company is derived via `employee→branch→company`). Active salary, position, and start date live in the active `Contract` — not on `Employee` directly. `employee.position_id` is a legacy field; always use `employee.activeContract.position`. Employee status flows through the contract lifecycle.

### Modules

| Module | Key classes |
|--------|------------|
| Payroll | `PayrollService`, `PayrollPeriod`, `Payroll`, `PayrollItem` |
| Perceptions | `PerceptionCalculator`, `EmployeePerception` |
| Deductions | `DeductionCalculator`, `EmployeeDeduction` |
| Loans & Advances | `LoanService`, `LoanInstallmentCalculator` |
| Vacations | `VacationService`, `VacationBalance` |
| Aguinaldo (13th) | `AguinaldoService`, `AguinaldoPeriod`, `AguinaldoItem` |
| Liquidación | `LiquidacionService`, `LiquidacionItem` |
| Attendance | `AttendanceDay`, `AttendanceEvent`, observers auto-calculate daily totals |
| Face Recognition | TensorFlow.js (128-element descriptors), `FaceEnrollment`, `FaceCaptureApp.js` |

### Service Layer
Business logic lives in `app/Services/`. Each domain has a `*Service` for orchestration and a `*Calculator` for isolated math. PDF generation is handled by dedicated generator classes in the same directory.

### Observers
`app/Observers/` — `AttendanceDayObserver` recalculates daily totals on event changes; `AttendanceEventObserver` validates timestamps; `EmployeeObserver` handles lifecycle hooks.

### Admin Panel (Filament)
- Resources: `app/Filament/Resources/` — one Resource per Model
- Pages: `app/Filament/Pages/` — `ManageGeneralSettings`, `ManagePayrollSettings`
- Widgets: `app/Filament/Widgets/` — dashboard stats, attendance today, expiring contracts

### Public Routes (no auth)
Kiosk/terminal marking and face enrollment run on public routes, served by their own JS entry points:
- `resources/js/attendances/mark.js` — facial clock-in
- `resources/js/attendances/terminal.js` — shared kiosk terminal
- `resources/js/enrollments/capture-face.js` — employee self-enrollment

### Status Enums
- Employee/Contract: `active`, `inactive`, `draft`, `suspended`
- Payroll: `draft` → `processing` → `approved` → `paid`
- Loans: `pending` → `active` → `paid` / `defaulted` / `cancelled`

### Key Config Files
- `config/payroll.php` — vacation tiers, payroll rules
- `config/attendance.php` — `ABSENCE_THRESHOLD_MINUTES`, face recognition settings
- `app/Settings/` — runtime settings via Filament Settings plugin (`GeneralSettings`, `PayrollSettings`)

### Important Notes
- Monetary values use `decimal:2` cast
- `Employee::getAdvanceReferenceSalary()` does **not** yet include the weekly paid rest day for jornaleros — pending automatic calculation
- Mobile mode is for remote employees using their own device, **not** a shared kiosk
- Terminal/kiosk mode is a shared device per branch
