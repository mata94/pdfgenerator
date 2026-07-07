# Feature 03 — Upload (Sprint 3)

**Status:** completed

## Goal
File upload pipeline following the Command → Handler → Service → Repository → Builder flow.

## Checklist
- [x] UploadPdfCommand + UploadPdfCommandHandler
- [x] PdfUploadService (store file in storage, create PdfJob record)
- [x] PdfJobRepository + Interface
- [x] PdfJobBuilder + PdfJobModel
- [x] PdfController::upload()
- [x] UploadZone.vue component (drag & drop + file select)

## Decision: multipart, not the SerializerManager, for this endpoint
CLAUDE.md's rule #1 ("always use the serializer, never `$request->input()`")
and its literal Controller example both assume a JSON body deserialized via
`SerializerManager::deserialize($request->getContent(), ...)`. That's
incompatible with a real file upload: `$request->getContent()` on a
multipart/form-data request isn't JSON, so it can't be fed to a JSON
deserializer. Asked the user; decided on plain `multipart/form-data` +
`$request->file('file')`, with the Command built manually in the controller
via setters (bypassing the serializer only for this endpoint, only because
of that technical constraint). `UploadPdfRequest` still does all validation.
Every other endpoint (Convert, etc.) keeps using `SerializerManager` on a
JSON body exactly as documented.

## Delivered
- `UploadPdfRequest` — validates `file` (required, max 20MB, mimes: pdf, doc,
  docx, xls, xlsx, ppt, pptx, jpg, jpeg, png) and `operation` (required,
  must be a valid `PdfOperation` enum value via `Rule::enum()`).
- `UploadPdfCommand` — getters/setters only: `uploadedFile`, `operation`,
  `sessionId`, `user`.
- `UploadPdfCommandHandler` — routes to `PdfUploadService::upload()`, wraps
  the result via `PdfJobBuilder::makeSingle()`.
- `PdfUploadService` — stores the file under
  `storage/app/private/pdf-jobs/{sessionId}/{uuid}.{ext}` (default `local`
  disk), creates the `PdfJob` row (`status=pending`, `expires_at` = +24h)
  via the repository.
- `PdfJobRepositoryInterface` + `PdfJobRepository` — `create()` only, for
  now; bound in `AppServiceProvider::register()`.
- `PdfJobBuilder` + `PdfJobModel` — exactly per the CLAUDE.md example.
- `PdfController::upload()` — builds the Command from the multipart
  request, sets user/session, executes the handler, returns JSON (422 on
  service exceptions).
- Route: `POST /api/v1/pdf/upload` → `api.v1.pdf.upload`.
- `resources/js/Components/UploadZone.vue` — drag & drop + click-to-select,
  styled per the CLAUDE.md Upload Zone spec (dashed border, red icon tile,
  "Drag PDF here or select a file", "Select file" button). Takes an
  `operation` prop, POSTs multipart via the existing `api` axios instance
  (`resources/js/bootstrap.js`), emits `uploaded` / `error`. Wired into
  `Home.vue`'s placeholder (hardcoded `operation="pdf_to_word"`) purely so
  it's visible and testable — the real tool-card-driven flow is Sprint 5.

## Fixed along the way (both pre-existing bugs, not introduced by this feature)
- **Inertia page hydration was completely broken on every page.** The
  installed `@inertiajs/vue3` is v3 (protocol v2), which only reads the
  initial page from a `<script data-page="app" type="application/json">`
  element (`getInitialPageFromDOM` in `@inertiajs/core`) — it no longer
  supports the old `<div id="app" data-page="...">` attribute format that
  `inertia-laravel`'s `@inertia` directive emits by default. Fixed by
  setting `INERTIA_USE_SCRIPT_ELEMENT_FOR_INITIAL_PAGE=true` in `.env` /
  `.env.example` (config already auto-merged, no publish needed). Without
  this, every page threw `Cannot read properties of null (reading
  'component')` and never mounted — Feature 02 was verified only via curl
  (which just checks the server-rendered HTML/props), never an actual
  browser JS execution, so this was never caught until this feature's
  browser test.
- **`Login.vue` crashed on mount** (`u.route is not a function`). Vue's
  template compiler doesn't resolve bare global identifiers like Ziggy's
  `route()` inside `<template>` — it rewrites them to `_ctx.route`, which
  is `undefined` unless the app registers `ZiggyVue`'s global property
  (we didn't install `ziggy-js`, relying on the `@routes` Blade directive
  alone). Calling `route()` from plain `<script setup>` code (as
  `UploadZone.vue` does, inside its `upload()` function) works fine —
  only inline template usage breaks. Fixed by hoisting
  `route('auth.google')` to a `const googleLoginUrl` in `<script setup>`
  and binding `:href="googleLoginUrl"` instead.

## Verified
- Backend, via curl multipart: valid upload → 200 with the expected
  `PdfJobModel` JSON, file lands under `storage/app/private/pdf-jobs/...`,
  `PdfJob` row correct (`status=pending`, `expires_at` +24h); invalid
  `operation` → 422 with a validation message.
- Frontend, via a real headless Chrome driven by Playwright (no
  `chromium-cli` in this environment, so scripted directly against
  `google-chrome-stable`): loaded `/`, confirmed zero console errors,
  clicked "Select file" → native file chooser → selected a file → upload
  succeeded → "Uploaded — job #N, status: pending" rendered. Separately
  simulated a real `dragover`/`drop` with a `DataTransfer` carrying a
  `File` — `.dragging` class applied on dragover, drop triggered the same
  successful upload path. Screenshots taken before/after. Also re-verified
  `/login` renders with zero console errors after the two fixes above.
- Test `PdfJob` rows and uploaded files cleaned up from the dev DB/disk
  after verification.

## Notes
- `PdfJobRepositoryInterface` only has `create()` right now — `find()` /
  etc. will be added in Sprint 4 (Convert/Get) when they're actually needed.
- `UploadZone.vue`'s placement in `Home.vue` is a temporary wiring for
  testability; Sprint 5 replaces `Home.vue` with the real hero + tool-card
  design and will drive `UploadZone` from the selected tool card's
  operation instead of a hardcoded prop.
