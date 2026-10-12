<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use App\Jobs\GenerateOutline;
use App\Jobs\StructureDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\Ai\GenerationLimiter;
use App\Services\Billing\PlanLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Document operations shared by MCP tools (and reusable from Livewire). Callers pass the acting user; nothing here reads auth().
 */
class DocumentService
{
    public function __construct(
        private SchemaValidator $validator,
        private ContentUpdater $updater,
        private PlanLimits $limits,
        private ImportParser $importer,
        private GenerationLimiter $generations,
    ) {}

    /**
     * @param  array<string, mixed>  $cover  extra cover fields (name, nim, prodi, campus, year)
     * @param  list<array<string, mixed>>  $chapters
     * @return array{document: Document|null, errors: list<string>}
     */
    public function create(User $user, string $title, DocumentType $type, EducationLevel $level, array $cover = [], array $chapters = []): array
    {
        $result = $this->createDraft($user, [
            'title' => $title,
            'type' => $type,
            'level' => $level,
            'cover' => $cover,
            'input' => 'chapters',
            'chapters' => $chapters,
        ]);

        return ['document' => $result['document'], 'errors' => $result['errors']];
    }

    /**
     * Create a draft from any input path, the one place web and MCP share. Input is expected
     * to be validated by the caller; this enforces plan quota, schema, and the AI budget.
     *
     * Inputs: `paste` (raw_text), `upload` (upload), `topic` (topic, chapter_count, length,
     * instructions, facts, sources), or `chapters` (ready-made chapters, no AI).
     *
     * @param  array{
     *     title: string, type: DocumentType, level: EducationLevel, input: 'paste'|'upload'|'topic'|'chapters',
     *     cover?: array<string, string>, style?: string, raw_text?: string, upload?: UploadedFile|null,
     *     chapters?: list<array<string, mixed>>, topic?: string, chapter_count?: int, length?: string,
     *     instructions?: string, facts?: array<string, string|list<string>>, sources?: list<string>
     * }  $draft
     * @return array{document: Document|null, errors: list<string>, field: string|null}
     */
    public function createDraft(User $user, array $draft, ?string $ip = null): array
    {
        $input = $draft['input'];
        $upload = $draft['upload'] ?? null;

        if ($input === 'upload' && $upload === null) {
            return $this->failed('upload', 'Pilih file yang akan diunggah.');
        }

        if (! $this->limits->canCreateDocument($user)) {
            return $this->failed('quota', 'Kuota dokumen bulan ini sudah habis. Upgrade ke Pro untuk membuat dokumen tanpa batas.');
        }

        $chapters = $draft['chapters'] ?? [];
        $brief = null;

        if ($input !== 'chapters') {
            $brief = [
                'input' => $input,
                'topic' => $input === 'topic' ? ($draft['topic'] ?? '') : '',
                'chapter_count' => $draft['chapter_count'] ?? $draft['type']->defaultChapterCount(),
                'length' => $draft['length'] ?? 'sedang',
                'instructions' => $input === 'topic' ? ($draft['instructions'] ?? '') : '',
            ];

            if ($input === 'paste') {
                $brief['raw_text'] = $draft['raw_text'] ?? '';
            }

            if ($input === 'topic' && ($draft['facts'] ?? []) !== []) {
                $brief['facts'] = $draft['facts'];
            }
        }

        if ($upload !== null && $input === 'upload') {
            try {
                $parsed = $this->importer->parse($upload);
            } catch (Throwable $e) {
                report($e);

                return $this->failed('upload', 'File tidak bisa dibaca. Pastikan berformat .docx, .md, atau .txt yang valid.');
            }

            $chapters = $parsed['chapters'] ?? [];

            if (isset($parsed['paragraphs'])) {
                $brief['paragraphs'] = $parsed['paragraphs'];
            }
        }

        $content = [
            'schema_version' => 1,
            'type' => $draft['type']->value,
            'cover' => ['title' => $draft['title']] + array_intersect_key($draft['cover'] ?? [], array_flip(['name', 'nim', 'prodi', 'campus', 'year'])),
            'style' => $draft['style'] ?? 'formal',
            'chapters' => $chapters,
            'references' => [],
            'sources' => $input === 'topic' ? $this->sourceEntries($draft['sources'] ?? []) : [],
        ];

        if ($brief !== null) {
            $content['brief'] = $brief;
        }

        if (($errors = $this->validator->validate($content)) !== []) {
            return match ($input) {
                'chapters' => ['document' => null, 'errors' => $errors, 'field' => null],
                'upload' => $this->failed('upload', 'Struktur heading di file tidak valid. Periksa judul bab dan subbab.'),
                default => $this->invalidDraft($input, $errors),
            };
        }

        $needsAi = $input === 'topic' || (in_array($input, ['paste', 'upload'], true) && $chapters === []);

        if ($needsAi && ! $this->generations->consumeFor($user, 1, $input, $ip)) {
            return $this->failed('generate', $this->generations->refusalMessage($user, 1, $ip));
        }

        if ($upload !== null && $input === 'upload') {
            $path = $upload->store('document-uploads/'.$user->id);

            if ($path === false) {
                return $this->failed('upload', 'File tidak bisa disimpan. Coba unggah ulang.');
            }

            $content['brief']['upload_path'] = $path;
        }

        try {
            $document = Document::create([
                'user_id' => $user->id,
                'title' => $draft['title'],
                'type' => $draft['type'],
                'education_level' => $draft['level'],
                'status' => DocumentStatus::Draft,
                'template_key' => 'default',
                'content' => $content,
            ]);

            if ($input === 'topic') {
                GenerateOutline::dispatch($document);
            } elseif ($needsAi) {
                StructureDocument::dispatch($document);
            }
        } catch (Throwable $e) {
            if (isset($content['brief']['upload_path'])) {
                Storage::delete($content['brief']['upload_path']);
            }

            throw $e;
        }

        return ['document' => $document, 'errors' => [], 'field' => null];
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{id: string, text: string, url: string|null, doi: null}>
     */
    private function sourceEntries(array $lines): array
    {
        return array_map(fn (string $line): array => [
            'id' => (string) Str::uuid(),
            'text' => $line,
            'url' => filter_var($line, FILTER_VALIDATE_URL) !== false ? $line : null,
            'doi' => null,
        ], $lines);
    }

    /**
     * @param  list<string>  $errors
     * @return array{document: null, errors: list<string>, field: string}
     */
    private function invalidDraft(string $input, array $errors): array
    {
        report(new RuntimeException('Draft content failed schema validation: '.implode('; ', $errors)));

        return $this->failed($input === 'topic' ? 'topic' : 'rawText', 'Data belum lengkap atau tidak valid. Periksa kembali isian.');
    }

    /** @return array{document: null, errors: list<string>, field: string} */
    private function failed(string $field, string $message): array
    {
        return ['document' => null, 'errors' => [$message], 'field' => $field];
    }

    /** Owner only; a missing and a foreign document look the same to the caller. */
    public function findFor(User $user, int $id): ?Document
    {
        return Document::where('user_id', $user->id)->find($id);
    }

    /**
     * Replace one chapter (matched by id) and validate the whole document before saving.
     *
     * @param  array<string, mixed>  $chapter
     * @return list<string>
     */
    public function updateChapter(Document $document, string $chapterId, array $chapter): array
    {
        $content = $document->content ?? [];
        $index = array_search($chapterId, array_column($content['chapters'] ?? [], 'id'), true);

        if ($index === false) {
            return ["Bab dengan id {$chapterId} tidak ditemukan."];
        }

        $content['chapters'][$index] = ['id' => $chapterId] + $chapter;

        return $this->updater->apply($document, $content);
    }

    /** Temporary signed URL (10 minutes); Free users cannot export PDF when the plan says so. */
    public function exportUrl(User $user, Document $document, string $format): ?string
    {
        if ($format === 'pdf' && ! $this->limits->canExportPdf($user)) {
            return null;
        }

        // No session on MCP clients: the signed URL names the owner and format (see McpDownloadController).
        return URL::temporarySignedRoute('mcp.download', now()->addMinutes(10), ['document' => $document->id, 'user' => $user->id, 'format' => $format]);
    }
}
