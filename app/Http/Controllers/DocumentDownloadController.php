<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Documents\DocxBuilder;
use App\Services\Documents\PdfConverter;
use App\Services\Documents\SchemaValidator;
use App\Services\Documents\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document, Request $request, DocxBuilder $builder, PdfConverter $pdf, SchemaValidator $validator): BinaryFileResponse
    {
        Gate::authorize('view', $document);

        $content = $document->content;

        if (empty($content['chapters'] ?? null) || $validator->validate($content) !== []) {
            abort(422, 'Dokumen belum punya isi yang valid untuk diunduh. Lengkapi bab dan subbabnya dulu.');
        }

        $templateKey = config("documents.templates.{$document->template_key}") ? $document->template_key : 'default';
        $path = $builder->build($content, Template::fromConfig($templateKey));

        $name = Str::slug($document->title) ?: 'dokumen';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        if ($request->query('format') === 'pdf') {
            try {
                $pdfPath = $pdf->convert($path);
            } catch (Throwable $e) {
                report($e);
                abort(503, 'Konversi PDF sedang tidak tersedia. Coba lagi sebentar lagi, atau unduh DOCX.');
            } finally {
                @unlink($path);
            }

            return response()->download($pdfPath, $name.'.pdf', [], $disposition)->deleteFileAfterSend();
        }

        return response()->download($path, $name.'.docx', [], $disposition)->deleteFileAfterSend();
    }
}
