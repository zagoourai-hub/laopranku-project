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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Ambil isi dokumen milik pengguna (JSON document-v1).')]
#[IsReadOnly]
class GetDocument extends Tool
{
    public function handle(Request $request, DocumentService $documents): Response
    {
        /** @var User $user */
        $user = $request->user();
        $id = $request->validate(['id' => ['required', 'integer']])['id'];
        $document = $documents->findFor($user, $id);

        if ($document === null) {
            return Response::error('Dokumen tidak ditemukan.');
        }

        return Response::json([
            'id' => $document->id,
            'title' => $document->title,
            'type' => $document->type->value,
            'education_level' => $document->education_level->value,
            'status' => $document->status->value,
            'content' => $document->content,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->description('ID dokumen')->required()];
    }
}
