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

#[Description('Ganti satu bab (cocok lewat chapter_id) dengan bab baru {title, sections[{id, title, blocks[]}]}. Seluruh dokumen divalidasi; bila ada yang tidak valid tidak ada yang disimpan.')]
class UpdateChapter extends Tool
{
    public function handle(Request $request, DocumentService $documents): Response
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'chapter_id' => ['required', 'string'],
            'chapter' => ['required', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $document = $documents->findFor($user, $data['id']);

        if ($document === null) {
            return Response::error('Dokumen tidak ditemukan.');
        }

        $errors = $documents->updateChapter($document, $data['chapter_id'], $data['chapter']);

        return $errors === [] ? Response::text('Bab tersimpan.') : Response::error(implode("\n", $errors));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID dokumen')->required(),
            'chapter_id' => $schema->string()->description('UUID bab yang diganti')->required(),
            'chapter' => $schema->object()->description('Isi bab baru: title dan sections')->required(),
        ];
    }
}
