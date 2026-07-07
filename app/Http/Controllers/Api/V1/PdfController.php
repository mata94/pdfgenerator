<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Commands\Pdf\ConvertPdfCommand;
use App\Application\Commands\Pdf\ConvertPdfCommandHandler;
use App\Application\Commands\Pdf\UploadPdfCommand;
use App\Application\Commands\Pdf\UploadPdfCommandHandler;
use App\Application\Query\Pdf\GetPdfQuery;
use App\Application\Query\Pdf\GetPdfQueryHandler;
use App\Domain\Pdf\Repositories\Interfaces\PdfJobRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pdf\ConvertPdfRequest;
use App\Http\Requests\Pdf\UploadPdfRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use WayOfDev\Serializer\Manager\SerializerManager;

class PdfController extends Controller
{
    public function __construct(
        private SerializerManager $serializer
    ) {
    }

    public function upload(UploadPdfRequest $request, UploadPdfCommandHandler $handler)
    {
        $command = new UploadPdfCommand();
        $command->setUploadedFile($request->file('file'));
        $command->setOperation($request->input('operation'));
        $command->setUser(auth()->user());
        $command->setSessionId(session()->getId());

        try {
            $result = $handler->execute($command);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function convert(ConvertPdfRequest $request, ConvertPdfCommandHandler $handler)
    {
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

    public function show(int $id, GetPdfQueryHandler $handler)
    {
        $query = new GetPdfQuery();
        $query->setId($id);

        try {
            $result = $handler->execute($query);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json($result);
    }

    public function download(int $id, PdfJobRepositoryInterface $repository)
    {
        $job = $repository->find($id);

        if (! $job || ! $job->output_file) {
            return response()->json(['error' => 'File not available.'], 404);
        }

        return Storage::disk('local')->download($job->output_file);
    }
}
