<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Documents\DocxBuilder;
use App\Services\Documents\SchemaValidator;
use App\Services\Documents\Template;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document, DocxBuilder $builder, SchemaValidator $validator): BinaryFileResponse
    {
        Gate::authorize('view', $document);

        $content = $document->content;

        if (empty($content['chapters'] ?? null) || $validator->validate($content) !== []) {
            abort(422, 'Dokumen belum punya isi yang valid untuk diunduh. Lengkapi bab dan subbabnya dulu.');
        }

        $templateKey = config("documents.templates.{$document->template_key}") ? $document->template_key : 'default';
        $path = $builder->build($content, Template::fromConfig($templateKey));

        return response()
            ->download($path, (Str::slug($document->title) ?: 'dokumen').'.docx')
            ->deleteFileAfterSend();
    }
}
