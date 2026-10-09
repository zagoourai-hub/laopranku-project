<?php

namespace App\Mcp\Tools;

use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Buat dokumen draft baru. `chapters` opsional (struktur document-v1: id uuid, title, sections[id, title, blocks[]]); judul tanpa nomor/kata BAB.')]
class CreateDocument extends Tool
{
    public function handle(Request $request, DocumentService $documents): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'type' => ['required', Rule::enum(DocumentType::class)],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'cover' => ['nullable', 'array'],
            'chapters' => ['nullable', 'array'],
        ]);

        $result = $documents->create(
            $user,
            $data['title'],
            DocumentType::from($data['type']),
            EducationLevel::from($data['education_level'] ?? 'mahasiswa'),
            $data['cover'] ?? [],
            $data['chapters'] ?? [],
        );

        if ($result['document'] === null) {
            return Response::error(implode("\n", $result['errors']));
        }

        return Response::json(['id' => $result['document']->id, 'title' => $result['document']->title, 'status' => $result['document']->status->value]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Judul dokumen')->required(),
            'type' => $schema->string()->enum(array_column(DocumentType::cases(), 'value'))->description('Jenis dokumen')->required(),
            'education_level' => $schema->string()->enum(array_column(EducationLevel::cases(), 'value'))->description('Jenjang; default mahasiswa'),
            'cover' => $schema->object()->description('Opsional: name, nim, prodi, campus, year'),
            'chapters' => $schema->array()->description('Opsional: daftar bab sesuai document-v1'),
        ];
    }
}
