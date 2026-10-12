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

#[Description('Buat dokumen draft. input: `chapters` (bab siap pakai, tanpa AI), `paste` (raw_text, AI menata struktur), atau `topic` (AI menulis outline lalu bab, butuh data pokok untuk praktikum/PKL). Judul tanpa nomor/kata BAB. Kuota paket berlaku sama seperti di web.')]
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
            'input' => ['nullable', Rule::in(['chapters', 'paste', 'topic'])],
            'cover' => ['nullable', 'array'],
            'chapters' => ['nullable', 'array'],
            'raw_text' => ['string', 'max:200000', Rule::requiredIf(fn () => ($this->inputOf($request)) === 'paste')],
            'topic' => ['string', 'min:10', 'max:500', Rule::requiredIf(fn () => ($this->inputOf($request)) === 'topic')],
            'chapter_count' => ['nullable', 'integer', 'between:3,7'],
            'length' => ['nullable', Rule::in(['ringkas', 'sedang', 'panjang'])],
            'style' => ['nullable', Rule::in(['formal', 'semi_formal', 'ringkas'])],
            'instructions' => ['nullable', 'string', 'max:500'],
            'facts' => ['nullable', 'array'],
            'sources' => ['nullable', 'array', 'max:50'],
            'sources.*' => ['string', 'max:500'],
        ]);

        $type = DocumentType::from($data['type']);
        $input = $data['input'] ?? 'chapters';

        $result = $documents->createDraft($user, [
            'title' => $data['title'],
            'type' => $type,
            'level' => EducationLevel::from($data['education_level'] ?? 'mahasiswa'),
            'input' => $input,
            'cover' => $data['cover'] ?? [],
            'style' => $data['style'] ?? 'formal',
            'chapters' => $data['chapters'] ?? [],
            'raw_text' => $data['raw_text'] ?? '',
            'topic' => $data['topic'] ?? '',
            'chapter_count' => $data['chapter_count'] ?? $type->defaultChapterCount(),
            'length' => $data['length'] ?? 'sedang',
            'instructions' => $data['instructions'] ?? '',
            'facts' => $data['facts'] ?? [],
            'sources' => $data['sources'] ?? [],
        ]);

        if ($result['document'] === null) {
            return Response::error(implode("\n", $result['errors']));
        }

        return Response::json([
            'id' => $result['document']->id,
            'title' => $result['document']->title,
            'status' => $result['document']->status->value,
            'generating' => $input !== 'chapters',
        ]);
    }

    /** Rules that depend on `input` read it from the request, since the field itself is nullable. */
    private function inputOf(Request $request): string
    {
        return (string) ($request->get('input') ?? 'chapters');
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Judul dokumen')->required(),
            'type' => $schema->string()->enum(array_column(DocumentType::cases(), 'value'))->description('Jenis dokumen')->required(),
            'education_level' => $schema->string()->enum(array_column(EducationLevel::cases(), 'value'))->description('Jenjang; default mahasiswa'),
            'input' => $schema->string()->enum(['chapters', 'paste', 'topic'])->description('Jalur isi; default chapters (tanpa AI)'),
            'cover' => $schema->object()->description('Opsional: name, nim, prodi, campus, year'),
            'chapters' => $schema->array()->description('Untuk input chapters: bab sesuai document-v1'),
            'raw_text' => $schema->string()->description('Untuk input paste: teks mentah'),
            'topic' => $schema->string()->description('Untuk input topic: topik, minimal 10 karakter'),
            'chapter_count' => $schema->integer()->description('Jumlah bab 3-7 (topic)'),
            'length' => $schema->string()->enum(['ringkas', 'sedang', 'panjang'])->description('Panjang per bab (topic)'),
            'style' => $schema->string()->enum(['formal', 'semi_formal', 'ringkas'])->description('Gaya bahasa'),
            'instructions' => $schema->string()->description('Instruksi tambahan, maks 500 karakter (topic)'),
            'facts' => $schema->object()->description('Data pokok untuk praktikum (experiment_title, objective, materials[], steps[], observations) atau PKL (institution, division, period, activities[]). Wajib untuk topic di kedua jenis ini.'),
            'sources' => $schema->array()->description('Sumber referensi, satu string per sumber (topic)'),
        ];
    }
}
