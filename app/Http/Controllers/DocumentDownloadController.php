<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Billing\PlanLimits;
use App\Services\Documents\DocumentExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document, Request $request, DocumentExporter $exporter, PlanLimits $plans): BinaryFileResponse
    {
        Gate::authorize('view', $document);

        $format = $request->query('format') === 'pdf' ? 'pdf' : 'docx';

        if ($format === 'pdf' && ! $plans->canExportPdf($request->user())) {
            abort(403, 'Unduh PDF khusus paket Pro. Upgrade untuk membuka fitur ini, atau unduh DOCX.');
        }

        $path = $exporter->export($document, $format);
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response()
            ->download($path, (Str::slug($document->title) ?: 'dokumen').'.'.$format, [], $disposition)
            ->deleteFileAfterSend();
    }
}
