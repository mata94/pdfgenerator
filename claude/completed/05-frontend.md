# Feature 05 — Frontend (Sprint 5)

**Status:** completed

## Goal
Build the Inertia/Vue UI per the Design spec in CLAUDE.md.

## Checklist
- [x] Home.vue (hero + upload zone + all tool cards)
- [x] ToolCard.vue component
- [x] EmailCaptureModal.vue (modal on download for guests)
- [x] GuestLimitBanner.vue
- [x] Dashboard.vue (list of previous conversions for logged-in users)
- [x] Responsive design (mobile-friendly)

## Design decisions (not fully specified in CLAUDE.md, decided here)
- **Icons**: CLAUDE.md names literal Tabler Icons webfont classes
  (`ti-file-word`, etc). The matching npm package (`@tabler/icons-webfont`)
  unpacks to ~125MB just for icon-font assets — used inline SVGs instead,
  color-matched exactly to the CLAUDE.md Icons-by-File-Type table, grouped
  by `fileType` (word/excel/pptx/photo/pdf) so every card sharing a format
  looks consistent.
- **Upload → Convert is now one flow, not two.** Sprint 3/4 exposed upload
  and convert as separate API calls; the UI calls both back-to-back the
  moment a file is picked (via `useConversionFlow.run()`), since the
  operation is already decided by the time a file is selected — there's no
  extra user decision to wait for. `UploadZone.vue` was reworked from
  Feature 03's self-uploading component into a pure UI trigger (emits
  `(file, operation)`, does no API calls itself) — anticipated in Feature
  03's own notes ("Sprint 5 ... will drive UploadZone from the selected
  tool card's operation instead of a hardcoded prop").
- **Hero UploadZone vs. ToolCard**: the hero drop zone accepts any
  supported file and auto-derives the operation from its extension
  (`pdfOperations.js:operationForFile` — a bare `.pdf` defaults to
  **compress**, since that's the one unambiguous generic action for a
  PDF with no stated target; every other extension has exactly one
  sensible target already). Each `ToolCard` instead opens a file picker
  scoped to its own specific operation via `accept`, so clicking "PDF to
  Word" can't be satisfied with a `.docx`.
- **Download is a blob fetch, not a link**, because the actual
  `/api/v1/pdf/{id}/download` route is POST (intentionally, so
  `check.guest.limit` + `increment.guest.usage` gate it) — a plain
  `<a href>` can't hit a POST route. `pdfApi.js:downloadJob()` fetches with
  `responseType: 'blob'` and `triggerBlobDownload()` synthesizes a
  click on an object-URL anchor. The `downloadUrl` already on
  `PdfJobModel` (a `Storage::temporaryUrl`) is used directly only on the
  **Dashboard**, where the user is already authenticated and
  guest-gating doesn't apply.
- **EmailCaptureModal always prompts guests on every download** (no
  "remembered for this session" state) — matches CLAUDE.md's stated flow
  literally and avoids an extra bit of state to track for a decision with
  no clear right answer.

## Delivered
- `resources/css/app.css` — added `--color-background-primary`,
  `--color-border-tertiary`, `--color-text-primary`,
  `--color-text-secondary`, `--border-radius-lg` custom properties (none of
  these existed yet; CLAUDE.md's spec references them as if already
  defined).
- `resources/js/pdfOperations.js` — the 11 operations (10 from
  `PdfOperation` + `compress` from Feature 04) with label, description,
  `accept`, and `fileType`; `operationForFile()` extension→operation
  mapping; `findOperation()` lookup.
- `resources/js/pdfApi.js` — `uploadFile`, `convertJob`, `getJob`,
  `saveGuestEmail`, `downloadJob`, `triggerBlobDownload`. Centralizes every
  API call the frontend makes so components stay UI-only.
- `resources/js/useConversionFlow.js` — composable owning
  `status`/`job`/`errorCode`/`errorMessage` + `run()`/`reset()`/`fail()`.
- `resources/js/Layouts/AppLayout.vue` — shared navbar (logo, Login/Register
  for guests, Dashboard/Log out for authed users) + slot. Not in the
  checklist by name, but the Navbar is required by the Design spec and
  Home/Dashboard both need it — one shared file beats duplicating it twice.
- `resources/js/Components/UploadZone.vue` — reworked to pure UI (drag/drop
  + click, auto-detects operation, emits `selected`/`error`).
- `resources/js/Components/ToolCard.vue`, `EmailCaptureModal.vue`,
  `GuestLimitBanner.vue` — per checklist.
- `resources/js/Pages/Home.vue` — hero, guest note, result panel
  (uploading/converting/completed/error states), guest-limit banner,
  11-card tool grid, email modal wiring.
- `resources/js/Pages/Dashboard.vue` — job list (operation label, status
  badge, date, direct download link since the user is already
  authenticated).
- Backend support for Dashboard (not in any prior Sprint's checklist, but
  required for this page to show real data): `PdfJobRepositoryInterface`/
  `PdfJobRepository` gained `forUser(int $userId)`; `PdfJobBuilder` gained
  `makeCollection()`; `/dashboard` route now resolves both and passes
  `jobs` as an Inertia prop instead of rendering a static page.

## Verified
Full Playwright pass against the live Docker stack (`google-chrome-stable`,
no `chromium-cli` in this environment), zero console errors throughout:
- Home page renders all 11 tool cards with correct icons/colors/copy
  (screenshot matches the design spec).
- Dropped a PDF into the hero zone → auto-detected `compress` → result
  panel showed "Uploading…" → "Converting…" → "Compress PDF complete." →
  clicked Download → guest email modal appeared → submitted email → real
  file download fired (`{uuid}-compressed.pdf`).
- Clicked the "PDF to JPG" tool card directly → scoped native file picker
  → uploaded/converted/downloaded correctly.
- Guest limit: 3 full convert+download rounds succeed; the 4th `convert`
  is blocked and the `GuestLimitBanner` renders in place of the result
  panel (a real 403 is expected in the console here — the code path that
  catches and renders it was exercised, not bypassed).
- Dashboard: empty state for a fresh user ("Start a conversion" link);
  after converting and downloading (bypassing the email modal, confirmed
  it does **not** show for authenticated users), the job appears with a
  COMPLETED badge and working direct download link.
- Mobile viewport (375×812): hero copy wraps, tool grid collapses to two
  columns, Login page card stays centered and usable.
- All test `PdfJob`/`LoginToken`/`GuestUsage`/`User` rows and uploaded
  files cleaned up from the dev DB/disk afterward.

## Notes
- Sprint 6 (Polish) still needs: scheduled cleanup of expired
  `pdf_jobs`/files (`expires_at` is already set on upload but nothing
  reaps it yet), global error handling, rate limiting, SMTP fix (see
  Feature 04's notes — real Gmail creds are configured but currently
  rejected).
