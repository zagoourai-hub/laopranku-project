<?php

namespace App\Services\Documents;

use App\Models\Document;
use Throwable;

/**
 * Builds the DOCX (or the PDF converted from it) for a document and returns the temporary file path.
 * Shared by the browser download and the MCP download so both stay identical. Aborts with 422/503 like a controller.
 */
class DocumentExporter
{
    public function __construct(
        private DocxBuilder $builder,
        private PdfConverter $pdf,
        private SchemaValidator $validator,
    ) {}

    public function export(Document $document, string $format): string
    {
        $content = $document->content;

        if (empty($content['chapters'] ?? null) || $this->validator->validate($content) !== []) {
            abort(422, 'Dokumen belum punya isi yang valid untuk diunduh. Lengkapi bab dan subbabnya dulu.');
        }

        $templateKey = config("documents.templates.{$document->template_key}") ? $document->template_key : 'default';
        $path = $this->builder->build($content, Template::fromConfig($templateKey), $document->education_level);

        if ($format !== 'pdf') {
            return $path;
        }

        try {
            return $this->pdf->convert($path);
        } catch (Throwable $e) {
            report($e);
            abort(503, 'Konversi PDF sedang tidak tersedia. Coba lagi sebentar lagi, atau unduh DOCX.');
        } finally {
            @unlink($path);
        }
    }
}
