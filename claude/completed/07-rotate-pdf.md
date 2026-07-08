# Feature 07 — Rotate PDF

**Status:** completed

## Goal
Let a user rotate the pages of a PDF by 90°, 180°, or 270° and download the
rotated result. First of five new tools (07–11); introduces the shared
"params at upload" plumbing that 08–11 will reuse.

## Checklist
- [x] `PdfOperation::ROTATE` enum case
- [x] "Params at upload" plumbing: `UploadPdfRequest` → `UploadPdfCommand` →
      `PdfController::upload` → `PdfUploadService` → `pdf_jobs.options`
- [x] `QpdfProcessor` (shared processor for 07–10) with `rotate()`
- [x] `PdfConversionService` match arm for `ROTATE`
- [x] Dockerfile: added `qpdf`
- [x] Frontend: `pdfOperations.js` entry, `pdfApi.js`/`useConversionFlow.js`
      forward `options`, new `OperationOptionsModal.vue`, wired into `Home.vue`
- [x] Tests + end-to-end verification

## Delivered
- **`PdfOperation::ROTATE = 'rotate'`** added to the enum.
- **Params-at-upload plumbing** (reused as-is by Features 08–11):
  `UploadPdfRequest` validates a nullable `options` array
  (`options.angle` required-if-rotate, one of 90/180/270; `options.pages`
  optional string); `UploadPdfCommand` gained a plain `?array $options`
  getter/setter; `PdfController::upload()` sets it from
  `$request->input('options')`; `PdfUploadService::upload()` persists it into
  `pdf_jobs.options`. `PdfJob::$fillable`/`casts()` already had `options` (JSON
  cast to array) from the original schema — no model change needed.
- **`QpdfProcessor`** (`app/Infrastructure/Pdf/Processors/QpdfProcessor.php`) —
  `rotate($inputPath, $outputPath, $angle, $pages)` shells out to
  `qpdf --rotate=+{angle}:{range}`. The whole `--rotate=...` flag is built as
  one string and passed through a single `escapeshellarg()` (qpdf's syntax is
  one token `+angle:range`, not two separate args). Exit code 3 (warnings-only,
  output still valid) is treated as success alongside 0 — the same pattern
  Ghostscript/LibreOffice processors don't need but qpdf commonly returns.
- **`PdfConversionService`** — injected `QpdfProcessor`, added the `ROTATE`
  match arm reading `$job->options['angle']` / `['pages']`.
- **Frontend**: `pdfOperations.js` gained a `rotate` entry with an
  `optionsSchema` (a new convention: `[{ key, type, label, options?, required }]`
  describing the params UI). `pdfApi.js::uploadFile()` and
  `useConversionFlow.js::run()` both gained an optional `options` param;
  `uploadFile` appends `options[key]=value` fields to the `FormData` (bracket
  notation — Laravel parses this into a nested array automatically, verified
  manually, see below). New **`OperationOptionsModal.vue`** (styled like
  `EmailCaptureModal.vue`) renders `select`/`text`/`password` fields from
  `optionsSchema`; `Home.vue`'s `startConversion()` now opens this modal first
  when the selected operation has a non-empty `optionsSchema`, otherwise
  converts immediately as before (existing operations unaffected).

## Infrastructure
- **Dockerfile**: added `qpdf` to the `apt-get install` line (alongside
  libreoffice/ghostscript/imagemagick). Rebuilt via `docker compose build app`
  — rebuild initially failed with a build-context permission error caused by
  root-owned leftover test/session directories under
  `storage/app/private/pdf-jobs/*` (from earlier dev/test runs where the
  container's php-fpm runs as root — `USER www-data` is commented out in the
  Dockerfile). These are gitignored runtime artifacts, not tracked work.
  **Added a `.dockerignore`** excluding `storage/app/*`, `storage/logs/*`,
  `storage/framework/{cache,sessions,testing,views}/*`, `.git`, `node_modules`,
  `vendor` — this both fixes the permission issue at its root and is correct
  practice anyway, since `docker-compose.yml` bind-mounts the whole repo over
  `/var/www` at runtime, making the `COPY . .` copy of runtime storage
  contents into the image pointless. Rebuilt cleanly afterward; `qpdf --version`
  confirmed inside the container (`qpdf version 12.2.0`).

## Verified
- **Automated**: `php artisan test` → **25 passed (97 assertions)**, including
  two new tests in `ConversionPipelineTest.php`:
  - `rotate succeeds and produces a valid rotated pdf` (angle 90, asserts job
    completes and the download starts with `%PDF`).
  - `rotate requires a valid angle` (angle 45 → 422 on `options.angle`).
  `tests/Pest.php`'s `convertOperation()` helper gained an optional `$options`
  array param, filtered out of the request when null so every other existing
  call site (which passes no options) is unaffected.
- **Manual end-to-end, real HTTP stack** (not just Pest's in-process client):
  drove the actual `nginx` container on `localhost:82` with `curl`, replicating
  exactly what the browser's `FormData` sends — `-F "options[angle]=90"`
  bracket-notation multipart fields (rather than JSON, which is what the Pest
  helper sends) — to confirm Laravel parses bracket-notation multipart fields
  into the nested `options` array correctly (it does). Full upload → convert →
  download round trip confirmed via `strings`/`qpdf --show-pages` on the
  downloaded bytes: `/Rotate 90` and, in a second run, `/Rotate 180` were
  present on the page object, absent from the original. Invalid angle (45) via
  the same real-HTTP path correctly returned `422` with a validation error body.
  `npm run build` (Vite) succeeded with no errors, confirming the new
  `OperationOptionsModal.vue` and modified `Home.vue`/`pdfApi.js`/
  `useConversionFlow.js`/`pdfOperations.js` compile cleanly.
- Test `PdfJob` rows and files created during the manual curl verification were
  deleted from the dev DB/storage afterward via tinker.

## Notes for Features 08–11
- Reuse `QpdfProcessor` for Protect (`encrypt()`), Unlock (`decrypt()`), and
  Watermark (`overlay()`) — the exit-code-3-is-success handling and the
  single-`escapeshellarg`-per-flag pattern should carry over.
- The `options.*` validation keys are additive per operation
  (`required_if:operation,<op>`) in the same `UploadPdfRequest` — no new file
  needed, just more rules.
- `OperationOptionsModal.vue`'s `optionsSchema` convention already supports
  `password` and generic `text` field types (used by 08/09/10), not just
  `select` — no changes needed there for those features.
