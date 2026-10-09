<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use App\Services\Documents\DocumentExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download link handed out by the MCP export tool. MCP clients have no browser session, so access comes
 * from the signed URL alone: it carries the owner's id and the format, and the signature covers both.
 */
class McpDownloadController extends Controller
{
    public function __invoke(int $document, Request $request, DocumentExporter $exporter, PlanLimits $plans): BinaryFileResponse
    {
        $doc = Document::find($document);
        $owner = User::find((int) $request->query('user'));
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'docx';

        abort_unless($doc && $owner && $doc->user_id === $owner->id && ! $owner->is_guest, 403);

        if ($format === 'pdf' && ! $plans->canExportPdf($owner)) {
            abort(403, 'Unduh PDF khusus paket Pro.');
        }

        return response()
            ->download($exporter->export($doc, $format), (Str::slug($doc->title) ?: 'dokumen').'.'.$format)
            ->deleteFileAfterSend();
    }
}
