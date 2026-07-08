# Feature 09 — Unlock PDF

**Status:** completed

## Goal
Let a user remove the password from a password-protected PDF by supplying the
current password, producing an unencrypted downloadable PDF.

## Checklist
- [x] `PdfOperation::UNLOCK` enum case
- [x] `UploadPdfRequest` validation for `options.password` (shared with Protect)
- [x] `QpdfProcessor::decrypt()`
- [x] `PdfConversionService` match arm for `UNLOCK`
- [x] Wrong-password → friendly 422, not a 500
- [x] Frontend: `pdfOperations.js` entry
- [x] Tests + end-to-end verification

## Delivered
- **`PdfOperation::UNLOCK = 'unlock'`** added to the enum.
- **`UploadPdfRequest`** — the existing `options.password` rule (from Feature
  08) was extended from `required_if:operation,protect` to
  `required_if:operation,protect,unlock` rather than adding a second rule —
  both operations share the same key and shape.
- **`QpdfProcessor::decrypt()`** — shells out to
  `qpdf --password={pw} --decrypt {in} {out}`. Deviated slightly from the
  original plan's marker-string (`unlock_wrong_password`) + controller-side
  mapping: instead, on qpdf exit code `2` (wrong/missing password) the
  processor throws `RuntimeException('Wrong password — could not unlock the
  PDF.')` directly. This reaches the client unchanged — `PdfConversionService`
  catches `RuntimeException`, marks the job `failed`, and rethrows;
  `PdfController::convert()` catches `\Exception` and returns
  `['error' => $e->getMessage()], 422`. No new mapping layer needed since the
  message is already client-appropriate at the point it's thrown — one less
  moving part than the originally sketched approach.
- **`PdfConversionService`** — added the `UNLOCK` match arm.
- **Frontend** — `pdfOperations.js` gained an `unlock` entry (password field,
  labeled "Current password" to distinguish it from Protect's "Password").
  No `OperationOptionsModal.vue` or `useConversionFlow.js` changes needed — the
  existing error-message fallback chain (`data.message ?? data.error ?? ...`)
  already surfaces the friendly qpdf message from the 422 body as-is.

## Verified
- **Automated**: `php artisan test` → **30 passed (120 assertions)** (3 new
  tests in `ConversionPipelineTest.php`, plus a new `encryptedPdfUpload()`
  fixture helper added to `tests/Pest.php` that shells out to real `qpdf
  --encrypt` to produce a genuinely encrypted fixture — consistent with this
  project's "real binaries, not fake bytes" testing philosophy):
  - `unlock succeeds and produces a decrypted pdf` — decrypts a real encrypted
    fixture with the right password, then confirms via `qpdf --show-encryption`
    on the downloaded bytes that it prints "File is not encrypted".
  - `unlock fails clearly with the wrong password` — asserts the convert
    response is `422` with the exact friendly error body, and that the job
    row is left in `status: failed` (not silently swallowed).
  - `unlock requires a password` — 422 on `options.password`.
- **Manual end-to-end, real HTTP stack**: built an encrypted fixture with
  `qpdf --encrypt` inside the container, copied it to the host, and drove
  `nginx`/`app` on `localhost:82` via `curl` with the browser's exact
  multipart form. Wrong password → `422` with
  `{"error":"Wrong password — could not unlock the PDF."}`; correct password →
  job completes, and the downloaded file, copied back into the container and
  checked with `qpdf --show-encryption`, confirms "File is not encrypted".
  `npm run build` (Vite) succeeded with no errors.
- All test `PdfJob` rows, encrypted fixtures, and temp files from manual
  verification cleaned up from the dev DB/storage and container `/tmp`
  afterward.

## Notes for Feature 10 (Watermark)
- `QpdfProcessor::overlay()` can follow the same shape as `rotate()`/
  `encrypt()`/`decrypt()`: single `escapeshellarg` per flag, `EXIT_WARNINGS`
  constant reused, exception built only from qpdf's own stdout/stderr.
- Watermark's stamp-generation step is new territory (Ghostscript/PDF-lib, not
  qpdf) — no direct precedent in 07–09 beyond the general
  generate-temp-file-then-clean-up-in-`finally` pattern already used by
  `PdfConversionService::pdfToExcel()`.
