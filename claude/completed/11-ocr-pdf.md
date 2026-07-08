# Feature 11 — OCR PDF

**Status:** completed

## Goal
Make a scanned (image-only) PDF searchable by running OCR and embedding a text
layer, so the downloaded PDF has selectable/searchable text while keeping its
original appearance.

## Checklist
- [x] `PdfOperation::OCR` enum case
- [x] `UploadPdfRequest` validation for optional `options.language`
- [x] New `OcrProcessor` wrapping `ocrmypdf`
- [x] `PdfConversionService` match arm for `OCR`
- [x] Dockerfile: added `ocrmypdf tesseract-ocr`
- [x] Frontend: `pdfOperations.js` entry (no options step — none required)
- [x] Tests + end-to-end verification

## Delivered
- **`PdfOperation::OCR = 'ocr'`** added to the enum.
- **`UploadPdfRequest`** — added
  `'options.language' => ['nullable','string','max:32']` (no `required_if`;
  defaults to `eng` in the processor when absent).
- **New `OcrProcessor`** (`app/Infrastructure/Pdf/Processors/OcrProcessor.php`)
  — shells out to `ocrmypdf --skip-text -l {language} {in} {out}`.
  `--skip-text` leaves pages that already carry a text layer untouched instead
  of erroring, satisfying the "already-searchable PDF" acceptance criterion.
- **`PdfConversionService`** — injected `OcrProcessor`, added the `OCR` match
  arm reading `$job->options['language'] ?? 'eng'`.
- **Frontend** — `pdfOperations.js` gained a plain `ocr` entry with **no**
  `optionsSchema`, so `Home.vue`'s options-modal step is skipped entirely for
  this operation (the modal only opens when `optionsSchema?.length` is
  truthy) — exactly matching the plan's "no params step needed."

## Infrastructure
- **Dockerfile** — added `ocrmypdf tesseract-ocr` to the `apt-get install`
  line. Rebuilt via `docker compose build app` + `up -d app` — succeeded
  cleanly (`ocrmypdf 16.7.0+dfsg1`, `tesseract 5.5.0` confirmed in-container).
  No `tesseract-ocr-<lang>` packages added beyond the default English data,
  since only `eng` is exercised/exposed today.

## Verified
- **Automated**: `php artisan test` → **35 passed (141 assertions)** (2 new
  tests in `ConversionPipelineTest.php`, plus a new `scannedPdfUpload()`
  fixture helper in `tests/Pest.php` that renders real text onto a PNG via
  ImageMagick's `convert -annotate` and turns it into an image-only PDF —
  consistent with this project's "real binaries, not fake bytes" fixtures):
  - `ocr succeeds and embeds a searchable text layer` — important finding:
    OCR's text layer uses a subsetted font with remapped glyph codes, so
    neither a raw byte search nor the `qpdf --qdf` decompression trick used
    for Watermark can find the literal text. The reliable, non-redundant
    signal is to **re-run `ocrmypdf --skip-text` on the already-OCR'd
    output** — it only logs "skipping all processing on this page" when the
    page already carries a text layer, which is exactly what a successful OCR
    run produces.
  - `ocr on an already-text pdf completes without error` — running OCR on
    `fakePdfUpload()` (a fixture that already has visible text) completes
    with `status: completed` rather than failing, confirming `--skip-text`'s
    graceful-skip behavior end to end through the app's own pipeline.
  - Both tests ran fast (~1–2s) since the fixture images are tiny — OCR's
    documented performance cost scales with real-world page count/size, not
    reflected in these minimal fixtures.
- **Manual end-to-end, real HTTP stack**: generated a realistic scanned-style
  fixture (`convert -size 400x150 ... -annotate 0 "REAL OCR TEST"` → PDF) and
  drove `nginx`/`app` on `localhost:82` via `curl` with the browser's exact
  multipart form (no `options` field sent at all, matching the no-params-step
  UI). Full upload → convert → download round trip. For independent proof
  beyond the "skip on re-run" signal, extracted the embedded text directly
  from the downloaded file with **Ghostscript's `txtwrite` device**
  (`gs -sDEVICE=txtwrite`, already installed — no new dependency for this
  check) and confirmed it printed the exact recognized string,
  `REAL OCR TEST`, proving genuine OCR content, not just a "some text exists"
  signal. `npm run build` (Vite) succeeded with no errors.
- Test `PdfJob` row and all temp files (input/output fixtures, sidecars,
  intermediate images) from manual verification cleaned up from the dev
  DB/storage and container `/tmp` afterward.

## Future work (out of scope, unchanged from the original plan)
- Move OCR to an async queue with job-status polling — the project's sync
  queue blocks the HTTP request for the full OCR duration; acceptable for
  small uploads today but the strongest remaining scalability gap of the five
  new tools. The existing `pdf-conversion` rate limiter (15/min) already
  throttles abuse in the meantime.
- Broader language support / auto-detection beyond the default `eng`
  (`options.language` is already wired end-to-end and validated — adding a
  language selector to the frontend and installing more
  `tesseract-ocr-<lang>` packages is a small follow-up, not a redesign).

## Project status
All five new features (07–11: Rotate, Protect, Unlock, Watermark, OCR) are now
complete and in `claude/completed/`, alongside the original six sprints
(01–06).
