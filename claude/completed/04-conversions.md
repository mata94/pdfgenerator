# Feature 04 — Conversions (Sprint 4)

**Status:** completed

## Goal
Perform the actual file conversions via LibreOffice, Ghostscript, and ImageMagick processors.

## Checklist
- [x] ConvertPdfCommand + ConvertPdfCommandHandler
- [x] PdfConversionService (router that picks the right Processor per operation)
- [x] LibreOfficeProcessor (Word, PPTX, Excel conversions)
- [x] GhostscriptProcessor (compress)
- [x] ImageMagickProcessor (JPG, PNG conversions)
- [x] PdfController::convert() and download()
- [x] GetPdfQuery + GetPdfQueryHandler (fetch job status)

## Infrastructure
- **Dockerfile**: added `libreoffice ghostscript imagemagick` to the `app`
  image's apt packages; rebuilt the image (`docker compose build app` +
  `up -d app`). ImageMagick's default policy on this Debian trixie image
  doesn't restrict PDF read/write at all (no PDF policy line exists), so no
  policy.xml patch was needed.
- Added `App\Domain\Pdf\Enums\PdfOperation::COMPRESS = 'compress'` — none of
  the original 10 enum cases covered "compress", but CLAUDE.md's own
  `GhostscriptProcessor` doc ties it to a compress operation, and the Sprint
  4 checklist explicitly requires the processor — needed an operation to
  route to it.
- `composer require phpdocumentor/reflection-docblock` — the serializer
  package's `PhpDocExtractor` needs it; `PdfController` now has a
  constructor dependency on `SerializerManager` (for `convert()`), so it
  broke *every* controller method including `upload()`, not just the new
  ones. Missed in Feature 03 because nothing there needed the serializer
  resolved eagerly.

## Delivered
- `ConvertPdfRequest` (validates `pdfJobId` exists), `ConvertPdfCommand`
  (getters/setters only), `ConvertPdfCommandHandler` → `PdfConversionService`.
- `PdfJobRepositoryInterface`/`PdfJobRepository` gained `find()` and
  `update()`.
- `PdfConversionService::convert()` — loads the job, `match`es
  `PdfOperation` to the right processor call, resolves the absolute output
  path back to a disk-relative path (`Str::after($absolute, $diskRoot)`) for
  storage in `output_file`, sets `status` to `completed`/`failed`.
- Processors created per the CLAUDE.md examples, with two real fixes (see
  below): `LibreOfficeProcessor`, `GhostscriptProcessor`, `ImageMagickProcessor`.
- `GetPdfQuery`/`GetPdfQueryHandler` — straight repository lookup + builder,
  throws `ModelNotFoundException` (→ 404) when missing.
- `PdfController`: constructor now takes `SerializerManager` (used by
  `convert()`, matching the CLAUDE.md example exactly); `show()` builds a
  `GetPdfQuery` from the route param; `download()` takes
  `PdfJobRepositoryInterface` directly (no Command/Query fits a raw file
  stream response) and returns `Storage::disk('local')->download()`.
- Routes: `POST /api/v1/pdf/convert` (`check.guest.limit`),
  `GET /api/v1/pdf/{id}`, `POST /api/v1/pdf/{id}/download`
  (`check.guest.limit` + `increment.guest.usage`) — exactly as CLAUDE.md's
  Routes section specifies.

## Two real LibreOffice bugs found and fixed while testing
1. **Every LibreOffice conversion failed** with `dconf: unable to create
   directory '/var/www/.cache/dconf': Permission denied` /
   `User installation could not be completed` — `$HOME` for the php-fpm
   `www-data` process isn't writable. Fixed by adding
   `-env:UserInstallation=file://{unique tmp dir}` to every invocation
   (also sidesteps profile-lock conflicts between concurrent conversions);
   the temp profile dir is removed after each run.
2. **LibreOffice exits 0 even when the conversion actually failed.**
   `--convert-to docx` on a PDF prints `Error: no export filter ... found,
   aborting.` to stdout but still returns exit code 0 — the original
   exit-code-only check silently reported success while producing no output
   file. Fixed by also treating `Error:` in the captured output as failure.
   Root cause: LibreOffice opens a PDF as a **Draw** document by default,
   which has no Writer/Impress/Calc export filter. Needed an explicit
   `--infilter` per PDF-import case: `writer_pdf_import` for
   `PDF_TO_WORD`, `impress_pdf_import` for `PDF_TO_PPTX`.
   `LibreOfficeProcessor::convert()` gained an optional `$infilter` param.

## PDF → Excel (originally a limitation, later implemented — see addendum)
There is no `calc_pdf_import` filter (or any PDF-import path into Calc) in
this LibreOffice build — confirmed by testing directly. At the time this
sprint shipped, `PDF_TO_EXCEL` was wired identically to the other
LibreOffice operations and correctly marked the job `failed` with a clear
error rather than silently producing a bad file — but it could not succeed
with LibreOffice alone. **This was later fixed — see the addendum at the
bottom of this file.**

## Verified
Full upload → convert → show → download round trip against the live Docker
stack, logged in as a real user (to stay under the 3-conversion guest
limit while testing broadly):
- **Passed**, with correct file-type signatures on the downloaded bytes
  (checked via `file`): PDF→Word (docx), PDF→PPTX (pptx), PDF→JPG, PDF→PNG,
  JPG→PDF, PNG→PDF, Compress (gs-produced PDF/1.4), Word→PDF, PPTX→PDF.
- PDF→Excel: at sprint time, correctly ended in `status: failed` rather
  than a false "completed" (now implemented — see addendum).
- Excel→PDF wasn't independently tested (no `.xlsx` fixture on hand) but
  runs through the exact same `LibreOfficeProcessor::convert()` path already
  proven by Word→PDF and PPTX→PDF.
- Edge cases: `GET /api/v1/pdf/{missing-id}` → 404 with a clear error body;
  a fresh guest session gets exactly 3 successful `convert`+`download`
  pairs before the 4th `convert` is blocked with `guest_limit_reached`,
  confirming `check.guest.limit` and `increment.guest.usage` are wired
  correctly on the right routes.
- All test `PdfJob`/`LoginToken`/`GuestUsage`/`User` rows and uploaded/
  converted files cleaned up from the dev DB and disk afterward.

## Notes
- Bypassed the app's configured SMTP (real Gmail credentials the user set
  up, currently rejected — `535 5.7.8 Username and Password not accepted`)
  for test login by creating a `LoginToken` directly via tinker instead of
  sending real mail. Unrelated to this feature; flagging in case it matters
  for real usage.

## Addendum — PDF → Excel implemented + dconf warning fixed (post-Sprint 6)
The user hit the `PDF_TO_EXCEL` failure in real use and asked for it to work,
so the "known limitation" above was resolved with a two-step pipeline.

- **How it works now**: `PdfConversionService::pdfToExcel()` runs
  **PDF → CSV** via a new `TabulaProcessor` (tabula-java, which extracts
  tables from PDFs), then **CSV → XLSX** via the existing
  `LibreOfficeProcessor::convert(..., 'xlsx', 'CSV:44,34,UTF8')` — LibreOffice
  *can* import CSV into Calc, it just can't import PDF. The intermediate CSV
  is deleted afterward. Extraction quality is inherently PDF-dependent
  (bordered/ruled tables extract cleanly; borderless ones can merge
  columns) — that's a property of PDF table extraction, not a bug. If tabula
  finds no table, `TabulaProcessor` writes a placeholder line so LibreOffice
  still emits a valid (non-empty) workbook instead of erroring.
- **Tooling**: `tabula-1.0.5-jar-with-dependencies.jar` is downloaded to
  `/opt/tabula/tabula.jar` in the Dockerfile (reuses the JRE that LibreOffice
  already pulls in). `TabulaProcessor` reads the path from `TABULA_JAR`
  (default `/opt/tabula/tabula.jar`). Image rebuilt to bake it in.
- **dconf warning also fixed**: the `dconf-CRITICAL ... unable to create
  directory '/var/www/.cache/dconf'` noise (in the user's reported error and
  on every LibreOffice run) came from GTK/dconf trying to write under a
  non-writable `$HOME`. `LibreOfficeProcessor` now exports
  `HOME`/`XDG_CACHE_HOME` pointed at the same per-run temp profile dir, so
  the warning is gone (it was also polluting the captured output that the
  `Error:` check scans).
- **Verified**: unit/feature suite green with `pdf_to_excel` moved into the
  success dataset (23 tests, 87 assertions); a real bordered-table PDF driven
  through the actual browser UI (PDF-to-Excel tool card → convert → download)
  produced a valid `.xlsx` whose cells contained the extracted table
  (`Product/Qty/Price`, `Widget 10 2.50`, `Gadget 4 9.99`), zero console
  errors. Test data cleaned up afterward.
