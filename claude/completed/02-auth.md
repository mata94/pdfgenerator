# Feature 02 — Auth (Sprint 2)

**Status:** completed

## Goal
Authentication via Google Socialite and passwordless Magic Link, plus guest usage tracking.

## Checklist
- [x] Google Socialite setup (GoogleController + .env variables)
- [x] Magic Link (MagicLinkController + LoginToken model + MagicLinkMail)
- [x] Login.vue page (Google button + magic link email form)
- [x] GuestUsage logic (CheckGuestLimit middleware)
- [x] GuestController (saveEmail endpoint)

## Delivered
- `laravel/socialite` installed via composer.
- Config: `config/services.php` `google` block (`client_id`, `client_secret`,
  `redirect`); `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`/`GOOGLE_REDIRECT_URI`
  added to `.env` and `.env.example` (values blank — user must fill in real
  Google OAuth credentials to use the Google login button).
- `App\Http\Controllers\Auth\GoogleController` — `redirect()` / `callback()`,
  `updateOrCreate`s the `User` by email and logs them in.
- `App\Http\Controllers\Auth\MagicLinkController` — `send()` (creates a
  `LoginToken`, sha256-hashed, 30-minute expiry, mails the plain token),
  `login()` (validates + single-use consumes the token, `firstOrCreate`s the
  `User`, logs in), `logout()`.
- `App\Mail\MagicLinkMail` + `resources/views/emails/magic-link.blade.php`.
- `App\Http\Controllers\Api\V1\GuestController::saveEmail` — updates
  `guest_usage.email` only, never creates a `User` record directly.
- Routes wired in `routes/web.php`: auth routes, `home` / `login` /
  `dashboard` Inertia page routes, `api/v1/guest/email`.
- **Inertia scaffold** (not yet wired when this feature started, needed to
  ship anything Vue-based): registered `HandleInertiaRequests` in the `web`
  middleware group (`bootstrap/app.php`), added `auth.user` and
  `flash.status` to shared props, created `resources/views/app.blade.php`
  (Inertia root view, replaces the old Blade `welcome` view), rewrote
  `resources/js/app.js` to `createInertiaApp`. Removed the dead
  `vue-router`/`pinia` scaffold under `resources/js/{components,router,stores,views}`
  that shipped empty from the starter kit and was never wired up (`app.js`
  imported files that didn't exist, so nothing built before this).
- Pages: `resources/js/Pages/Auth/Login.vue` (Google button + magic-link
  form, styled per the CLAUDE.md design spec). `Home.vue` / `Dashboard.vue`
  are minimal placeholders — full design is Sprint 5.

## Verified
- Full request cycle checked against the live Docker stack (nginx + php-fpm
  + MySQL), not just route listing:
  - `route:list` shows all auth + page + guest routes.
  - `npm run build` succeeds; Inertia page payloads (`data-page`) render the
    right component (`Home`, `Auth/Login`, `Dashboard`) with `auth`/`flash`
    shared props.
  - Magic-link flow end-to-end: `POST /auth/magic-link` creates a
    `LoginToken` and logs the mail (MAIL_MAILER=log); following the emailed
    link logs the user in (`User` created via `firstOrCreate`), redirects
    home with `auth.user` populated; `/dashboard` is reachable once
    authenticated; re-visiting the same token 404s (single-use enforced).
  - `GET /auth/google` redirects (302) without fataling even with blank
    Google credentials.

## Fixed along the way
- `storage/` and `bootstrap/cache/` were bind-mounted from the host owned by
  a uid that php-fpm's `www-data` user couldn't write to (log/view/cache
  writes failed with "Permission denied", surfaced as a confusing tempnam()
  error during Laravel's own error-page rendering). Ran
  `chmod -R 777 storage bootstrap/cache` inside the `app` container to fix —
  needed for *any* route to serve without a 500, not specific to auth.

## Notes
- To actually use the Google login button, fill in `GOOGLE_CLIENT_ID` /
  `GOOGLE_CLIENT_SECRET` in `.env` from a real Google OAuth app.
- Magic-link emails currently just log (`MAIL_MAILER=log`) — switch to SMTP
  in `.env` per CLAUDE.md when real mail delivery is needed.
