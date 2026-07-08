# Feature 08 — Protect PDF

**Status:** completed

## Goal
Let a user add a password to a PDF (encrypt it), so the downloaded file requires
that password to open.

## Checklist
- [x] `PdfOperation::PROTECT` enum case
- [x] `UploadPdfRequest` validation for `options.password`
- [x] `QpdfProcessor::encrypt()`
- [x] `PdfConversionService` match arm for `PROTECT`
- [x] Frontend: `pdfOperations.js` entry (reuses `OperationOptionsModal.vue`'s
      existing `password` field type from Feature 07 — no component changes)
- [x] Tests + end-to-end verification

## Delivered
- **`PdfOperation::PROTECT = 'protect'`** added to the enum.
- **`UploadPdfRequest`** — added
  `'options.password' => ['required_if:operation,protect','string','min:1','max:255']`,
  alongside the existing `options.angle`/`options.pages` rules from Feature 07.
  The "params at upload" plumbing (`UploadPdfCommand`, `PdfController::upload`,
  `PdfUploadService`, `PdfJob.options`) needed **no changes** — it was already
  generic across operations.
- **`QpdfProcessor::encrypt()`** — shells out to
  `qpdf --encrypt {pw} {pw} 256 -- {in} {out}` (same password for user and
  owner, 256-bit AES). Reuses the `EXIT_WARNINGS = 3` success handling already
  established for `rotate()`.
- **`PdfConversionService`** — added the `PROTECT` match arm reading
  `$job->options['password']`.
- **Frontend** — `pdfOperations.js` gained a `protect` entry with
  `optionsSchema: [{ key: 'password', type: 'password', required: true }]`.
  `OperationOptionsModal.vue` already rendered a `password` input type (built
  generically in Feature 07 in anticipation of this), so no component changes
  were needed at all — confirms the Feature 07 plumbing was designed correctly
  for reuse.

## Security
- Password is never logged: `QpdfProcessor::encrypt()` doesn't log the built
  command, and the `RuntimeException` thrown on failure only includes qpdf's
  own stdout/stderr (`$output`), not the command line or the password itself.
- Password lives only in `pdf_jobs.options` (JSON column), purged by the
  existing `pdf:cleanup-expired` scheduled command along with the files after
  `expires_at` (24h) — no new retention path introduced.

## Infrastructure
None — `qpdf` was already added to the Dockerfile in Feature 07.

## Verified
- **Automated**: `php artisan test` → **27 passed (107 assertions)** (2 new
  tests added to `ConversionPipelineTest.php`):
  - `protect succeeds and produces a password-encrypted pdf` — converts, then
    shells out to real `qpdf --decrypt` twice on the downloaded bytes: without
    a password it must fail (exit `2`, invalid password — proves the file is
    genuinely encrypted, not just renamed), with the correct password it must
    succeed (exit `0` or `3`).
  - `protect requires a password` — omitting `options.password` → 422 on
    `options.password`.
- **Manual end-to-end, real HTTP stack**: drove `nginx`/`app` on `localhost:82`
  with `curl` using the exact bracket-notation multipart form the browser
  sends (`-F "options[password]=MySecret42"`). Full upload → convert →
  download round trip; copied the downloaded bytes into the container and
  confirmed with `qpdf --show-encryption` (`R = 6`, AES-256) and by actually
  attempting `qpdf --decrypt`: no password → exit `2` ("invalid password"),
  correct password → exit `3` (warnings-only, due to the intentionally minimal
  test fixture PDF — same as every other processor test in this project).
  Missing-password upload confirmed 422 via the same real-HTTP path.
  `npm run build` (Vite) succeeded with no errors.
- Test `PdfJob` row and temp files from manual verification cleaned up from the
  dev DB/storage and container `/tmp` afterward.

## Notes for Features 09–10
- `QpdfProcessor::decrypt()` (Unlock, Feature 09) and `overlay()` (Watermark,
  Feature 10) can follow the exact same shape as `encrypt()`: single
  `escapeshellarg` per flag, `EXIT_WARNINGS` constant already defined and
  reusable, exception message built only from qpdf's own output.
- The `options.password` validation key is already shared between Protect and
  will be reused as-is for Unlock (`required_if:operation,unlock` needs to be
  added alongside the existing `required_if:operation,protect` rule).
