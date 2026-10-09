<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Buat tautan unduhan sementara (10 menit) untuk DOCX atau PDF. PDF hanya untuk paket Pro bila paket Free dibatasi.')]
class ExportDocument extends Tool
{
    public function handle(Request $request, DocumentService $documents): Response
    {
        $data = $request->validate(['id' => ['required', 'integer'], 'format' => ['required', 'in:docx,pdf']]);
        /** @var User $user */
        $user = $request->user();
        $document = $documents->findFor($user, $data['id']);

        if ($document === null) {
            return Response::error('Dokumen tidak ditemukan.');
        }

        if (empty($document->content['chapters'] ?? null)) {
            return Response::error('Dokumen belum punya isi untuk diekspor.');
        }

        $url = $documents->exportUrl($user, $document, $data['format']);

        return $url === null
            ? Response::error('Ekspor PDF hanya tersedia untuk paket Pro.')
            : Response::json(['url' => $url, 'expires_in_minutes' => 10]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID dokumen')->required(),
            'format' => $schema->string()->enum(['docx', 'pdf'])->description('Format berkas')->required(),
        ];
    }
}
