# PDF Generator — Claude Code Instructions

## Project Overview

Web application for PDF conversion and processing. Users can upload files and convert them to various formats. Guests can use the app 3 times without logging in, after which registration is required.

---

## Tech Stack

- **Backend:** Laravel (latest stable) + PHP 8.2
- **Frontend:** Vue 3 + Inertia.js
- **Auth:** Laravel Socialite (Google) + Custom Magic Link (no password)
- **Storage:** Laravel Cloud Storage
- **Queue:** Sync (no Redis for now)
- **Mail:** SMTP
- **Serializer:** `wayofdev/laravel-serializer` (WayOfDev SerializerManager)
- **PDF Tools:** LibreOffice + Ghostscript + ImageMagick (local Docker services)
- **Routing:** Ziggy (`tightenco/ziggy`)

---

## Folder Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── GoogleController.php
│   │   │   └── MagicLinkController.php
│   │   └── Api/
│   │       └── V1/
│   │           ├── PdfController.php
│   │           └── GuestController.php
│   ├── Middleware/
│   │   └── CheckGuestLimit.php
│   └── Requests/
│       └── Pdf/
│           ├── UploadPdfRequest.php
│           └── ConvertPdfRequest.php
│
├── Application/
│   ├── Commands/
│   │   └── Pdf/
│   │       ├── UploadPdfCommand.php
│   │       ├── UploadPdfCommandHandler.php
│   │       ├── ConvertPdfCommand.php
│   │       └── ConvertPdfCommandHandler.php
│   └── Query/
│       └── Pdf/
│           ├── GetPdfQuery.php
│           └── GetPdfQueryHandler.php
│
├── Domain/
│   └── Pdf/
│       ├── Services/
│       │   ├── PdfUploadService.php
│       │   ├── PdfConversionService.php
│       │   └── GuestUsageService.php
│       ├── Repositories/
│       │   ├── Interfaces/
│       │   │   ├── PdfJobRepositoryInterface.php
│       │   │   └── GuestUsageRepositoryInterface.php
│       │   ├── PdfJobRepository.php
│       │   └── GuestUsageRepository.php
│       └── Enums/
│           ├── PdfOperation.php
│           └── PdfJobStatus.php
│
├── Infrastructure/
│   └── Pdf/
│       └── Processors/
│           ├── LibreOfficeProcessor.php
│           ├── GhostscriptProcessor.php
│           └── ImageMagickProcessor.php
│
├── Presentation/
│   └── Pdf/
│       ├── Models/
│       │   └── PdfJobModel.php
│       └── Builders/
│           └── PdfJobBuilder.php
│
├── Models/
│   ├── User.php
│   ├── LoginToken.php
│   ├── PdfJob.php
│   └── GuestUsage.php
│
└── Mail/
    └── MagicLinkMail.php
```

---

## Database

### Migration: users
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name')->nullable();
    $table->string('email')->unique();
    $table->string('google_id')->nullable();
    $table->string('avatar')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamps();
});
```

### Migration: login_tokens
```php
Schema::create('login_tokens', function (Blueprint $table) {
    $table->id();
    $table->string('email')->index();
    $table->string('token', 64)->unique();
    $table->string('redirect_to')->nullable();
    $table->timestamp('expires_at');
    $table->timestamp('used_at')->nullable();
    $table->timestamp('created_at')->nullable();
});
```

### Migration: pdf_jobs
```php
Schema::create('pdf_jobs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('session_id')->index();
    $table->string('input_file');
    $table->string('output_file')->nullable();
    $table->string('operation', 50);
    $table->string('status', 20)->default('pending');
    $table->json('options')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();
});
```

### Migration: guest_usage
```php
Schema::create('guest_usage', function (Blueprint $table) {
    $table->id();
    $table->string('session_id')->unique()->index();
    $table->string('email')->nullable();
    $table->tinyInteger('usage_count')->default(0);
    $table->timestamps();
});
```

---

## Enums

### PdfOperation.php
```php
<?php
namespace App\Domain\Pdf\Enums;

enum PdfOperation: string
{
    case PDF_TO_WORD  = 'pdf_to_word';
    case PDF_TO_PPTX  = 'pdf_to_pptx';
    case PDF_TO_EXCEL = 'pdf_to_excel';
    case PDF_TO_JPG   = 'pdf_to_jpg';
    case PDF_TO_PNG   = 'pdf_to_png';

    case WORD_TO_PDF  = 'word_to_pdf';
    case PPTX_TO_PDF  = 'pptx_to_pdf';
    case EXCEL_TO_PDF = 'excel_to_pdf';
    case JPG_TO_PDF   = 'jpg_to_pdf';
    case PNG_TO_PDF   = 'png_to_pdf';
}
```

### PdfJobStatus.php
```php
<?php
namespace App\Domain\Pdf\Enums;

enum PdfJobStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
}
```

---

## Architecture — Request Flow

```
HTTP Request
     ↓
FormRequest (validation)
     ↓
Controller
     ↓
SerializerManager::deserialize($request->getContent(), XxxCommand::class)
     ↓
$command->setUser(auth()->user())
$command->setSessionId(session()->getId())
     ↓
CommandHandler::execute($command)
     ↓
Service (business logic + processor call)
     ↓
Repository (DB operations)
     ↓
Handler receives result → Builder::makeSingle($pdfJob)
     ↓
PdfJobModel (implements JsonSerializable)
     ↓
Controller → response()->json($model)
```

---

## Command Example

```php
<?php
namespace App\Application\Commands\Pdf;

use App\Models\User;

class UploadPdfCommand
{
    private string $filePath;
    private string $originalName;
    private string $sessionId;
    private ?User $user = null;

    public function getFilePath(): string { return $this->filePath; }
    public function setFilePath(string $filePath): void { $this->filePath = $filePath; }

    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $originalName): void { $this->originalName = $originalName; }

    public function getSessionId(): string { return $this->sessionId; }
    public function setSessionId(string $sessionId): void { $this->sessionId = $sessionId; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): void { $this->user = $user; }
}
```

---

## Handler Example

```php
<?php
namespace App\Application\Commands\Pdf;

use App\Domain\Pdf\Services\PdfUploadService;
use App\Presentation\Pdf\Builders\PdfJobBuilder;
use App\Presentation\Pdf\Models\PdfJobModel;

class UploadPdfCommandHandler
{
    public function __construct(
        private PdfUploadService $pdfUploadService,
        private PdfJobBuilder $builder
    ) {}

    public function execute(UploadPdfCommand $command): PdfJobModel
    {
        $pdfJob = $this->pdfUploadService->upload($command);
        return $this->builder->makeSingle($pdfJob);
    }
}
```

---

## Builder + Model Example

```php
<?php
namespace App\Presentation\Pdf\Builders;

use App\Models\PdfJob;
use App\Presentation\Pdf\Models\PdfJobModel;
use Illuminate\Support\Facades\Storage;

class PdfJobBuilder
{
    public function makeSingle(PdfJob $job): PdfJobModel
    {
        $model = new PdfJobModel();
        $model->setId($job->id);
        $model->setStatus($job->status);
        $model->setOperation($job->operation ?? '');
        $model->setDownloadUrl(
            $job->output_file
                ? Storage::temporaryUrl($job->output_file, now()->addHour())
                : null
        );
        $model->setCreatedAt($job->created_at->toIso8601String());
        return $model;
    }
}
```

```php
<?php
namespace App\Presentation\Pdf\Models;

class PdfJobModel implements \JsonSerializable
{
    private int $id;
    private string $status;
    private string $operation;
    private ?string $downloadUrl;
    private string $createdAt;

    public function getId(): int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }

    public function getOperation(): string { return $this->operation; }
    public function setOperation(string $operation): void { $this->operation = $operation; }

    public function getDownloadUrl(): ?string { return $this->downloadUrl; }
    public function setDownloadUrl(?string $downloadUrl): void { $this->downloadUrl = $downloadUrl; }

    public function getCreatedAt(): string { return $this->createdAt; }
    public function setCreatedAt(string $createdAt): void { $this->createdAt = $createdAt; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'status'      => $this->status,
            'operation'   => $this->operation,
            'downloadUrl' => $this->downloadUrl,
            'createdAt'   => $this->createdAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
```

---

## Controller Example

```php
<?php
namespace App\Http\Controllers\Api\V1;

use App\Application\Commands\Pdf\UploadPdfCommand;
use App\Application\Commands\Pdf\UploadPdfCommandHandler;
use App\Application\Commands\Pdf\ConvertPdfCommand;
use App\Application\Commands\Pdf\ConvertPdfCommandHandler;
use App\Http\Requests\Pdf\UploadPdfRequest;
use App\Http\Requests\Pdf\ConvertPdfRequest;
use WayOfDev\Serializer\Manager\SerializerManager;

class PdfController extends Controller
{
    public function __construct(
        private SerializerManager $serializer
    ) {}

    public function upload(
        UploadPdfRequest $request,
        UploadPdfCommandHandler $handler
    ) {
        $command = $this->serializer->deserialize(
            $request->getContent(),
            UploadPdfCommand::class
        );
        $command->setUser(auth()->user());
        $command->setSessionId(session()->getId());

        try {
            $result = $handler->execute($command);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function convert(
        ConvertPdfRequest $request,
        ConvertPdfCommandHandler $handler
    ) {
        $command = $this->serializer->deserialize(
            $request->getContent(),
            ConvertPdfCommand::class
        );
        $command->setUser(auth()->user());
        $command->setSessionId(session()->getId());

        try {
            $result = $handler->execute($command);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
```

---

## Auth — Magic Link

```php
// MagicLinkController.php

public function send(Request $request)
{
    $request->validate(['email' => 'required|email']);

    $email    = strtolower($request->email);
    $plain    = Str::random(64);
    $redirect = $request->input('redirect_to', '/');

    LoginToken::create([
        'email'       => $email,
        'token'       => hash('sha256', $plain),
        'expires_at'  => Carbon::now()->addMinutes(30),
        'redirect_to' => $redirect,
    ]);

    Mail::to($email)->send(new MagicLinkMail($plain, $email, $redirect));

    return back()->with('status', 'Check your email for the login link.');
}

public function login(string $token)
{
    $hashed = hash('sha256', $token);

    $loginToken = LoginToken::where('token', $hashed)
        ->whereNull('used_at')
        ->where('expires_at', '>', now())
        ->firstOrFail();

    $user = User::firstOrCreate(
        ['email' => $loginToken->email],
        ['name'  => null]
    );

    $loginToken->update(['used_at' => now()]);

    auth()->login($user);

    return redirect($loginToken->redirect_to ?? '/');
}
```

---

## Auth — Google Socialite

```php
// GoogleController.php

public function redirect()
{
    return Socialite::driver('google')->redirect();
}

public function callback()
{
    $googleUser = Socialite::driver('google')->user();

    $user = User::updateOrCreate(
        ['email' => $googleUser->getEmail()],
        [
            'name'      => $googleUser->getName(),
            'google_id' => $googleUser->getId(),
            'avatar'    => $googleUser->getAvatar(),
        ]
    );

    auth()->login($user);

    return redirect('/');
}
```

---

## Middleware — CheckGuestLimit

```php
<?php
namespace App\Http\Middleware;

use App\Models\GuestUsage;
use Closure;
use Illuminate\Http\Request;

class CheckGuestLimit
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            return $next($request);
        }

        $usage = GuestUsage::firstOrCreate(
            ['session_id' => session()->getId()],
            ['usage_count' => 0]
        );

        if ($usage->usage_count >= 3) {
            return response()->json([
                'error'   => 'guest_limit_reached',
                'message' => 'Please log in to continue.',
            ], 403);
        }

        return $next($request);
    }
}
```

---

## Middleware — IncrementGuestUsage

Applied only to the download route:

```php
public function handle(Request $request, Closure $next)
{
    $response = $next($request);

    if (!auth()->check() && $response->isSuccessful()) {
        GuestUsage::where('session_id', session()->getId())
            ->increment('usage_count');
    }

    return $response;
}
```

---

## Routes

```php
// routes/web.php

// Auth
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
Route::post('/auth/magic-link', [MagicLinkController::class, 'send'])->name('auth.magic-link.send');
Route::get('/auth/magic-link/{token}', [MagicLinkController::class, 'login'])->name('auth.magic-link.login');
Route::post('/auth/logout', [MagicLinkController::class, 'logout'])->name('auth.logout')->middleware('auth');

// Inertia Pages
Route::get('/', fn() => Inertia::render('Home'))->name('home');
Route::get('/login', fn() => Inertia::render('Auth/Login'))->name('login');
Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->middleware('auth')->name('dashboard');

// API V1
Route::prefix('api/v1')->name('api.v1.')->group(function () {

    Route::post('/pdf/upload', [PdfController::class, 'upload'])
        ->name('pdf.upload');

    Route::post('/pdf/convert', [PdfController::class, 'convert'])
        ->middleware('check.guest.limit')
        ->name('pdf.convert');

    Route::get('/pdf/{id}', [PdfController::class, 'show'])
        ->name('pdf.show');

    Route::post('/pdf/{id}/download', [PdfController::class, 'download'])
        ->middleware(['check.guest.limit', 'increment.guest.usage'])
        ->name('pdf.download');

    Route::post('/guest/email', [GuestController::class, 'saveEmail'])
        ->name('guest.email');
});
```

---

## PDF Processors

### LibreOfficeProcessor.php
Used for: PDF→Word, PDF→PPTX, PDF→Excel, Word→PDF, PPTX→PDF, Excel→PDF

```php
<?php
namespace App\Infrastructure\Pdf\Processors;

class LibreOfficeProcessor
{
    public function convert(string $inputPath, string $outputDir, string $format): string
    {
        $command = sprintf(
            'libreoffice --headless --convert-to %s %s --outdir %s 2>&1',
            escapeshellarg($format),
            escapeshellarg($inputPath),
            escapeshellarg($outputDir)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('LibreOffice conversion failed: ' . implode("\n", $output));
        }

        $filename = pathinfo($inputPath, PATHINFO_FILENAME) . '.' . $format;
        return $outputDir . '/' . $filename;
    }
}
```

### GhostscriptProcessor.php
Used for: Compress PDF

```php
<?php
namespace App\Infrastructure\Pdf\Processors;

class GhostscriptProcessor
{
    public function compress(string $inputPath, string $outputPath, string $quality = 'ebook'): string
    {
        $command = sprintf(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/%s -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>&1',
            $quality,
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Ghostscript compression failed: ' . implode("\n", $output));
        }

        return $outputPath;
    }
}
```

### ImageMagickProcessor.php
Used for: PDF→JPG, PDF→PNG, JPG→PDF, PNG→PDF

```php
<?php
namespace App\Infrastructure\Pdf\Processors;

class ImageMagickProcessor
{
    public function pdfToImage(string $inputPath, string $outputPath, string $format = 'jpg'): string
    {
        $command = sprintf(
            'convert -density 150 %s -quality 90 %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath . '.' . $format)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('ImageMagick conversion failed: ' . implode("\n", $output));
        }

        return $outputPath . '.' . $format;
    }

    public function imageToPdf(string $inputPath, string $outputPath): string
    {
        $command = sprintf(
            'convert %s %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath . '.pdf')
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('ImageMagick conversion failed: ' . implode("\n", $output));
        }

        return $outputPath . '.pdf';
    }
}
```

---

## Guest Email Capture Flow

When a guest clicks download:
1. Frontend shows modal "Enter your email to download"
2. POST `/api/v1/guest/email` with the email
3. Backend: if email already exists in `users` → only save to `guest_usage.email`, do not create a new user
4. Backend: if email does not exist → save to `guest_usage.email` (user is created only when they log in)
5. Download proceeds, `usage_count` is incremented

```php
// GuestController.php
public function saveEmail(Request $request)
{
    $request->validate(['email' => 'required|email']);

    GuestUsage::where('session_id', session()->getId())
        ->update(['email' => strtolower($request->email)]);

    return response()->json(['success' => true]);
}
```

---

## Composer Packages

```json
{
    "require": {
        "laravel/framework": "^11.0",
        "laravel/socialite": "^5.0",
        "inertiajs/inertia-laravel": "^1.0",
        "wayofdev/laravel-serializer": "^1.0",
        "tightenco/ziggy": "^2.0"
    },
    "require-dev": {
        "pestphp/pest": "^2.0",
        "pestphp/pest-plugin-laravel": "^2.0"
    }
}
```

## NPM Packages

```json
{
    "dependencies": {
        "vue": "^3.0",
        "@inertiajs/vue3": "^1.0",
        "@vitejs/plugin-vue": "^5.0",
        "axios": "^1.0"
    }
}
```

---

## Design — UI Specification

### Color Palette
```
Primary (accent): #E24B4A  ← PDF red
Primary hover:    #C93B3A
Hero background:  #FEF9F9
Card background:  var(--color-background-primary)
Border:           var(--color-border-tertiary)  ← 0.5px
Primary text:     var(--color-text-primary)
Secondary text:   var(--color-text-secondary)
```

### Icons by File Type
```
Word  (.docx) → ti-file-word     | bg: #E6F1FB | color: #185FA5  (blue)
Excel (.xlsx) → ti-table         | bg: #EAF3DE | color: #3B6D11  (green)
PPTX  (.pptx) → ti-presentation | bg: #FAEEDA | color: #854F0B  (amber)
JPG/PNG       → ti-photo         | bg: #FCEBEB | color: #E24B4A  (red)
PDF           → ti-file-text     | bg: #FCEBEB | color: #E24B4A  (red)
```

### Navbar
- Logo: icon in red box (32x32px, border-radius 8px) + text "PDF Generator"
- Right side: "Login" button (outline) + "Register" button (red fill)
- Font-size: 14px, font-weight: 500

### Hero Section
- Background: `#FEF9F9`
- H1: 32px, font-weight 500, letter-spacing -0.5px
- Subtitle: 15px, secondary color
- Upload zone: max-width 560px, centered, dashed border 1.5px #E24B4A
- Upload zone hover: background `#FEF3F3`
- Text below upload zone: "You can use 3 times without logging in" with green dot

### Upload Zone
```
border: 1.5px dashed #E24B4A
border-radius: var(--border-radius-lg)
padding: 36px 24px
background: white
```
Contents:
1. Upload icon (48x48, red background #FCEBEB, red icon)
2. Title "Drag PDF here or select a file" (15px, 500)
3. Subtitle with supported formats (13px, secondary)
4. Button "Select file" (red, 13px)

### Tool Cards
```
background: var(--color-background-primary)
border: 0.5px solid var(--color-border-tertiary)
border-radius: var(--border-radius-lg)
padding: 14px 16px
```
Grid: `repeat(auto-fit, minmax(140px, 1fr))`
Hover: `border-color: #E24B4A`, `background: #FEF9F9`

Each card:
1. Icon (34x34px, border-radius 8px, type-specific color)
2. Conversion name (13px, 500)
3. Format description (11px, secondary)

### Sections
- Section title: 13px, uppercase, letter-spacing 0.06em, secondary color
- Section spacing: padding 40px 32px

### Pages to build (Inertia/Vue)
1. `Home.vue` — Landing page (upload + tool cards)
2. `Auth/Login.vue` — Google + Magic Link form
3. `Dashboard.vue` — (auth only) list of previous conversions
4. Components:
    - `UploadZone.vue`
    - `ToolCard.vue`
    - `EmailCaptureModal.vue` — modal on download for guests
    - `GuestLimitBanner.vue` — banner when guest reaches the limit

---

## Development Order

### Sprint 1 — Setup & Architecture
- [ ] Laravel project + package installation (composer + npm)
- [ ] Create folder structure: Application, Domain, Infrastructure, Presentation
- [ ] Create all migrations and run them
- [ ] Create Enum classes (PdfOperation, PdfJobStatus)
- [ ] Create Eloquent models (User, LoginToken, PdfJob, GuestUsage)
- [ ] Register middleware (CheckGuestLimit, IncrementGuestUsage) in `bootstrap/app.php`
- [ ] Set up Ziggy for Vue routing

### Sprint 2 — Auth
- [ ] Google Socialite setup (GoogleController + .env variables)
- [ ] Magic Link (MagicLinkController + LoginToken model + MagicLinkMail)
- [ ] Login.vue page (Google button + magic link email form)
- [ ] GuestUsage logic (CheckGuestLimit middleware)
- [ ] GuestController (saveEmail endpoint)

### Sprint 3 — Upload
- [ ] UploadPdfCommand + UploadPdfCommandHandler
- [ ] PdfUploadService (store file in storage, create PdfJob record)
- [ ] PdfJobRepository + Interface
- [ ] PdfJobBuilder + PdfJobModel
- [ ] PdfController::upload()
- [ ] UploadZone.vue component (drag & drop + file select)

### Sprint 4 — Conversions
- [ ] ConvertPdfCommand + ConvertPdfCommandHandler
- [ ] PdfConversionService (router that picks the right Processor per operation)
- [ ] LibreOfficeProcessor (Word, PPTX, Excel conversions)
- [ ] GhostscriptProcessor (compress)
- [ ] ImageMagickProcessor (JPG, PNG conversions)
- [ ] PdfController::convert() and download()
- [ ] GetPdfQuery + GetPdfQueryHandler (fetch job status)

### Sprint 5 — Frontend
- [ ] Home.vue (hero + upload zone + all tool cards)
- [ ] ToolCard.vue component
- [ ] EmailCaptureModal.vue (modal on download for guests)
- [ ] GuestLimitBanner.vue
- [ ] Dashboard.vue (list of previous conversions for logged-in users)
- [ ] Responsive design (mobile-friendly)

### Sprint 6 — Polish
- [ ] Scheduled command for auto-deleting expired files (expires_at)
- [ ] Global error handling across the application
- [ ] Rate limiting on API routes
- [ ] SMTP configuration and test
- [ ] End-to-end testing of all conversions

---

## Environment Variables (.env)

```env
APP_NAME="PDF Generator"
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_DATABASE=pdf_generator

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@pdfgenerator.com"
MAIL_FROM_NAME="PDF Generator"

FILESYSTEM_DISK=local
```

---

## Notes for Claude Code

1. **Serializer** — always use `$this->serializer->deserialize($request->getContent(), XxxCommand::class)`, never `$request->input()` directly
2. **Command** — only getters and setters, zero logic inside the Command class
3. **Handler** — only redirects Command to Service and Service result to Builder, zero business logic
4. **Service** — all business logic lives here
5. **Repository** — all DB operations exclusively here, never directly in Service
6. **Builder** — `makeSingle()` method receives an Eloquent model, returns a Presentation Model object
7. **Presentation Model** — always implements `\JsonSerializable`, always has `toArray()` + `jsonSerialize()` methods
8. **Controller** — receives FormRequest, deserializes into Command, calls Handler, returns `response()->json($model)`, zero logic here
9. **Every API route = one Command or Query + Handler**
10. **Commands** = POST/PUT/DELETE routes (mutations)
11. **Query** = GET routes (read-only)

---

## Feature Tracking

All features are broken down into individual files located in:

```
claude/
├── features/       ← pending features (implement these next)
│   ├── 01-setup.md
│   ├── 02-auth.md
│   ├── 03-upload.md
│   ├── 04-conversions.md
│   ├── 05-frontend.md
│   └── 06-polish.md
└── completed/      ← move here when done
```

### ⚠️ IMPORTANT — Read before every task

**Before starting any work, always:**
1. Read all files in `claude/features/` to see what is pending
2. Find the lowest-numbered file that is NOT yet in `claude/completed/`
3. That is the next feature to implement
4. Only work on one feature at a time
5. When a feature is fully complete, remind the user to move the file from `claude/features/` to `claude/completed/`


> Update this table manually as features are completed: change ⬜ to ✅ and move the file to `claude/completed/`.
