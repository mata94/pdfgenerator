# Feature 06 — Polish (Sprint 6)

**Status:** completed

## Goal
Production hardening and final testing.

## Checklist
- [x] Scheduled command for auto-deleting expired files (expires_at)
- [x] Global error handling across the application
- [x] Rate limiting on API routes
- [~] SMTP configuration and test — see note below (user said SMTP is
      working; left untouched by request)
- [x] End-to-end testing of all conversions

## Delivered
- **Cleanup command**: `App\Console\Commands\CleanupExpiredPdfJobs`
  (`pdf:cleanup-expired`) deletes every `PdfJob` past its `expires_at` along
  with its stored `input_file` and `output_file`. Registered in
  `routes/console.php` via `Schedule::command('pdf:cleanup-expired')->hourly()`
  (Laravel 12 scheduling style). `app/Console/Commands/` didn't exist yet —
  created it; the command auto-discovers.
- **Global JSON error handling** in `bootstrap/app.php` `withExceptions()`:
  a single `render()` callback that, for `api/*` requests only, converts any
  thrown exception into a consistent `{"error": ...}` (plus `errors` for
  validation) JSON body with the right status — 422 validation, 401 auth,
  403 authorization, 404 model-not-found, passthrough for HTTP exceptions,
  500 otherwise. 500s are masked to a generic message unless `app.debug`.
  Non-api requests fall through to Laravel's normal (Inertia) handling.
- **Rate limiting**: named limiters defined in `AppServiceProvider::boot()`
  and applied in `routes/web.php`:
  - `api-default` (60/min) on the whole `api/v1` group.
  - `pdf-conversion` (15/min) on `upload` + `convert` — these shell out to
    LibreOffice/Ghostscript/ImageMagick, so they're the expensive ones.
  - `pdf-download` (20/min) on `download`.
  - `magic-link` (5/min, keyed on IP) on the magic-link send route, to stop
    someone email-bombing a stranger's inbox.
  Each is keyed on `user id ?: ip`.
- **Test suite** (`pestphp/pest` — see version note): 23 passing tests.
  - `ConversionPipelineTest` — the "end-to-end testing of all conversions"
    item: uploads a real minimal PDF/PNG, converts through the actual
    binaries, and asserts the downloaded bytes start with the correct magic
    number for PDF→Word/PPTX/JPG/PNG, PNG→PDF, and Compress. Also asserts
    PDF→Excel fails cleanly (documented LibreOffice limitation), and the
    404/validation error paths.
  - `UploadTest`, `GuestLimitTest`, `CleanupExpiredTest`, `RateLimitTest`.

## Fixes / decisions along the way
- **Removed `tests/Feature/Jobs/GenerateMealWeekJobTest.php`** — leftover
  from unrelated boilerplate (referenced `App\Jobs\GenerateMealWeekJob`,
  `App\Models\MealPlan`, `App\Services\MealPlanGenerator`, none of which
  exist here). It was the source of the `MealItem`/`unit` DB error seen back
  in Feature 02's logs. Also created a real `Database\Factories\UserFactory`
  (the `User` model referenced it but the file was missing), needed by the
  auth-related tests.
- **Real rate-limiting bug found and fixed.** The first pass used anonymous
  `throttle:15,1` at both the group and route level. Laravel's *unnamed*
  throttle derives its cache key only from `domain|ip` (no per-limit
  component), so stacking two anonymous throttles made them share one bucket
  and double-count — a guest hit 429 after ~4 requests instead of getting
  independent per-tier budgets. Verified via a Pest test that walked the
  count. Fixed by switching to **named** limiters (`RateLimiter::for(...)`),
  whose cache key is prefixed by the limiter name, giving each tier its own
  independent budget.
- **Pest version**: CLAUDE.md pins `pestphp/pest ^2.0`, but Pest 2 requires
  PHPUnit ^10 while this project is already on PHPUnit 11 (Laravel 12).
  Installed Pest 3 (`^3.8`) + `pest-plugin-laravel ^3.2` with `-W` instead —
  the ^2.0 constraint is simply incompatible with the rest of the locked
  stack. (This pulled a minor `laravel/framework` bump 12.52→12.62 as a
  transitive upgrade.)
- **Guest-limit tests are middleware-level, not full-HTTP-flow.** The gate
  is keyed on `session()->getId()`, and Laravel's HTTP test client rotates
  the session id every request under the `array` session driver (threading
  the encrypted session cookie back doesn't hold the id). Rather than fight
  the harness, `GuestLimitTest` drives `CheckGuestLimit` and
  `IncrementGuestUsage` directly with a pinned 40-char session id — that's
  where the real logic lives. The full guest-limit *HTTP* flow (3 free, 4th
  blocked, banner shown) was already browser-verified end to end in
  Features 04 and 05.

## Verified
- `php artisan test` → **23 passed (84 assertions)**, including all
  conversion operations exercising the real binaries.
- `pdf:cleanup-expired` verified both by a Pest test (expired job + files
  gone, live job + file kept) and manually against the live container.
- Rate limiting verified three ways: Pest test (16th upload → 429), a manual
  curl loop (15×422 then 429 with `{"error":"Too Many Attempts."}`), and it
  correctly returns JSON via the new global handler.
- Global error handler verified via curl: `/api/v1/pdf/{missing}` → 404
  `{"error":"PDF job ... not found."}`, unknown api route → 404 JSON.
- Post-change browser regression: full guest upload→convert→email-modal→
  download flow still works with zero console errors (the global exception
  handler touches every API response, so this confirms no fallout).
- Test data / uploaded files cleaned from the dev DB and disk afterward.

## Note on SMTP
The SMTP checklist item was left untouched at the user's explicit request
("smtp working dont tuch that"). `.env` keeps the user's Gmail SMTP
configuration as-is. (For reference: an earlier test send returned
`535 Username and Password not accepted`, but per the user this is working
on their side, so no code or config change was made.)

## Project status
All six sprints (01–06) are now complete and in `claude/completed/`.
