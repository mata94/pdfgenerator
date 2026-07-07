# Feature 01 — Setup & Architecture (Sprint 1)

**Status:** completed

## Goal
Bootstrap the project foundation: DDD folder structure, database schema, enums,
Eloquent models, guest middleware, and Ziggy for Vue routing.

## Checklist
- [x] Laravel project + package installation (Ziggy installed via composer)
- [x] Create folder structure: Application, Domain, Infrastructure, Presentation
- [x] Create all migrations and run them
- [x] Create Enum classes (PdfOperation, PdfJobStatus)
- [x] Create Eloquent models (User, LoginToken, PdfJob, GuestUsage)
- [x] Register middleware (CheckGuestLimit, IncrementGuestUsage) in `bootstrap/app.php`
- [x] Set up Ziggy for Vue routing

## Delivered
- Migrations: `users`, `login_tokens`, `pdf_jobs`, `guest_usage` (+ framework
  `cache`, `jobs`, `sessions` tables for the database drivers). All migrated.
- Enums: `App\Domain\Pdf\Enums\PdfOperation`, `App\Domain\Pdf\Enums\PdfJobStatus`.
- Models: `App\Models\{User, LoginToken, PdfJob, GuestUsage}`.
  - `PdfJob` casts `options => array`, `expires_at => datetime`; keeps
    `operation`/`status` as strings to match the documented Builder contract.
  - `GuestUsage` overrides `$table = 'guest_usage'`.
- Middleware: `CheckGuestLimit`, `IncrementGuestUsage`, aliased as
  `check.guest.limit` and `increment.guest.usage` in `bootstrap/app.php`.
- DDD scaffold folders created under `app/` (Application, Domain, Infrastructure,
  Presentation, plus Http/Controllers/{Auth,Api/V1}, Http/Requests/Pdf, Mail).
- Ziggy (`tightenco/ziggy`) installed.

## Notes
- FK ordering: `users` migrates before `pdf_jobs` so the `user_id` foreign key
  (nullOnDelete) resolves.
- Verified end-to-end via tinker: User → PdfJob relationship, JSON cast, and
  GuestUsage all work against the live MySQL container.
