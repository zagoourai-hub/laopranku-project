<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use App\Models\Document;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\URL;

/**
 * Document operations shared by MCP tools (and reusable from Livewire). Callers pass the acting user; nothing here reads auth().
 */
class DocumentService
{
    public function __construct(
        private SchemaValidator $validator,
        private ContentUpdater $updater,
        private PlanLimits $limits,
    ) {}

    /**
     * @param  array<string, mixed>  $cover  extra cover fields (name, nim, prodi, campus, year)
     * @param  list<array<string, mixed>>  $chapters
     * @return array{document: Document|null, errors: list<string>}
     */
    public function create(User $user, string $title, DocumentType $type, EducationLevel $level, array $cover = [], array $chapters = []): array
    {
        if (! $this->limits->canCreateDocument($user)) {
            return ['document' => null, 'errors' => ['Kuota dokumen paket Free bulan ini sudah habis. Upgrade ke Pro untuk dokumen tanpa batas.']];
        }

        $content = [
            'schema_version' => 1,
            'type' => $type->value,
            'cover' => ['title' => $title] + array_intersect_key($cover, array_flip(['name', 'nim', 'prodi', 'campus', 'year'])),
            'style' => 'formal',
            'chapters' => $chapters,
            'references' => [],
            'sources' => [],
        ];

        if (($errors = $this->validator->validate($content)) !== []) {
            return ['document' => null, 'errors' => $errors];
        }

        $document = Document::create([
            'user_id' => $user->id,
            'title' => $title,
            'type' => $type,
            'education_level' => $level,
            'status' => DocumentStatus::Draft,
            'template_key' => 'default',
            'content' => $content,
        ]);

        return ['document' => $document, 'errors' => []];
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
