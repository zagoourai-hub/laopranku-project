<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PdfConverter
{
    /**
     * Convert a DOCX to PDF through Gotenberg (LibreOffice), refreshing the TOC and dropping blank pages.
     *
     * @return string absolute path of the generated .pdf next to the source file
     */
    public function convert(string $docxPath): string
    {
        $response = Http::timeout(120)
            ->attach('files', (string) file_get_contents($docxPath), basename($docxPath))
            ->post(config('documents.gotenberg_url').'/forms/libreoffice/convert', [
                'updateIndexes' => 'true',
                'skipEmptyPages' => 'true',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gotenberg conversion failed: HTTP '.$response->status().' '.$response->body());
        }

        $pdfPath = preg_replace('/\.docx$/', '.pdf', $docxPath);
        file_put_contents($pdfPath, $response->body());

        return $pdfPath;
    }
}
