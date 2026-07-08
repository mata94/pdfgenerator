# Feature 10 — Watermark PDF

**Status:** completed

## Goal
Let a user stamp a **text** watermark (e.g. "CONFIDENTIAL") diagonally across
every page of a PDF and download the result.

## Checklist
- [x] `PdfOperation::WATERMARK` enum case
- [x] `UploadPdfRequest` validation for `options.text`
- [x] New `WatermarkProcessor` (stamp generation) + `QpdfProcessor::overlay()`
- [x] `PdfConversionService` match arm + temp-stamp cleanup
- [x] Frontend: `pdfOperations.js` entry (reuses `OperationOptionsModal.vue`'s
      generic `text` field type — no component changes)
- [x] Tests + end-to-end verification

## Delivered
- **`PdfOperation::WATERMARK = 'watermark'`** added to the enum.
- **`UploadPdfRequest`** — added
  `'options.text' => ['required_if:operation,watermark','string','max:100']`.
- **`QpdfProcessor::overlay()`** — `qpdf --overlay {stamp} --repeat=1-z -- {in} {out}`.
  Confirmed by manual testing that `--repeat` refers to *repeating the
  (1-page) overlay source* once its own pages are exhausted, not a
  destination range — this correctly stamps every destination page from a
  single-page overlay file without needing `--to`.
- **New `WatermarkProcessor`** (`app/Infrastructure/Pdf/Processors/WatermarkProcessor.php`)
  generates the one-page stamp PDF:
  - `detectPageSize()` runs `qpdf --qdf --object-streams=disable {in} -` and
    regex-extracts the first `/MediaBox [...]`, so the stamp matches the real
    input's page size (falls back to US Letter 612×792 if detection fails).
  - `buildStampPdf()` hand-assembles a **complete, valid PDF from scratch**
    (own object table + proper xref, no external library) with a content
    stream that draws the text at 45° using the standard non-embedded
    Helvetica font, positioned via a text matrix (`Tm`) computed from an
    approximate character-width centering formula, light gray fill
    (`0.6 g`), font size clamped between 14–72pt scaled to page size.
  - Deviated from the plan's two suggested options (Ghostscript PostScript,
    or pulling in FPDF/TCPDF): went with **raw PDF byte construction in PHP**
    instead — no new binary or Composer dependency, and avoids PostScript
    string-escaping fragility. Text is sanitized to printable ASCII and PDF
    literal-string special characters (`\`, `(`, `)`) are escaped.
  - Chose a plain gray fill over true alpha transparency (which would need an
    `ExtGState`/`/ca` resource) — simpler, and configurable opacity was
    explicitly out of scope.
- **`PdfConversionService`** — added a private `watermark()` helper mirroring
  the existing `pdfToExcel()` temp-file pattern: generates the stamp next to
  the output path (`{output}.stamp.pdf`), calls
  `qpdfProcessor->overlay()`, deletes the stamp in a `finally` regardless of
  outcome.
- **Frontend** — `pdfOperations.js` gained a `watermark` entry with
  `optionsSchema: [{ key: 'text', type: 'text', ... }]`.
  `OperationOptionsModal.vue`'s generic text-input fallback (built in Feature
  07 for exactly this kind of field) needed no changes.

## Verified
- **Automated**: `php artisan test` → **33 passed (132 assertions)** (3 new
  tests in `ConversionPipelineTest.php`):
  - `watermark succeeds and stamps the text onto the pdf` — important
    finding: the watermark text lives inside a **Flate-compressed** content
    stream in the real output (qpdf recompresses by default), so a raw
    `strings`/byte search — which worked fine for `/Rotate` because that's a
    plain dictionary key — finds nothing. The test instead decompresses via
    `qpdf --qdf --object-streams=disable` and greps the human-readable dump,
    which does show the literal text.
  - `watermark requires text` — 422 on `options.text`.
  - `watermark cleans up its temporary stamp file` — asserts
    `{output}.stamp.pdf` does not exist on disk after conversion.
- **Manual multi-page verification** (outside the automated suite, since the
  project's fixture helpers are single-page): built a 2-page fixture and
  confirmed via the QDF dump that **both** pages' `/Resources/XObject`
  reference the watermark Form XObject (`/Fx1 Do`) — qpdf deduplicates the
  identical stamp object across pages, so a naive "count occurrences of the
  text" check undercounts; presence + per-page `Do` reference is the correct
  signal.
- **Manual end-to-end, real HTTP stack**: drove `nginx`/`app` on
  `localhost:82` via `curl` with the exact browser multipart form
  (`-F "options[text]=CONFIDENTIAL"`) against a Letter-sized (612×792)
  fixture. Full upload → convert → download round trip; the downloaded file,
  copied into the container, showed `/F1 72.00 Tf ... (CONFIDENTIAL) Tj` in
  the QDF dump (font size correctly clamped to the 72pt max for a large page)
  and passed `qpdf --check` with no structural errors. Missing-text upload
  confirmed 422 via the same real-HTTP path. Confirmed no leftover
  `*.stamp.pdf` files under `storage/app/private/pdf-jobs`. `npm run build`
  (Vite) succeeded with no errors.
- All test `PdfJob` rows and temp files from manual verification cleaned up
  from the dev DB/storage and container `/tmp` afterward.

## Future work (out of scope, unchanged from the original plan)
- Image/logo watermarks (second file upload).
- Configurable opacity, position, font size, and per-page vs tiled placement.

## Notes for Feature 11 (OCR)
- OCR uses its own binary (`ocrmypdf`) and its own new `OcrProcessor` — no
  `QpdfProcessor`/`WatermarkProcessor` reuse expected, per the original plan.
