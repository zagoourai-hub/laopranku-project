<?php

namespace App\Services\Documents;

use App\Models\Document;

class ContentUpdater
{
    public function __construct(private SchemaValidator $validator) {}

    /**
     * Validate and save edited content. Nothing is written unless the whole document is valid.
     *
     * @param  array<string, mixed>  $content
     * @return list<string> errors; empty when saved
     */
    public function apply(Document $document, array $content): array
    {
        if ($document->trashed()) {
            return ['Dokumen sudah dihapus dan tidak bisa diubah.'];
        }

        $errors = $this->validator->validate($content);

        if ($errors === [] && ($content['type'] ?? null) !== $document->type->value) {
            $errors[] = '/type: jenis dokumen tidak boleh diganti';
        }

        if ($errors !== []) {
            return $errors;
        }

        $document->content = $content;
        $document->title = $content['cover']['title'];
        $document->save();

        return [];
    }
}
