<?php

namespace App\Services\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Drafts only the outline: chapter and section titles with a short note each. It never writes body text.
 */
#[Temperature(0.4)]
#[Timeout(120)]
class OutlineAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TXT'
        Kamu menyusun OUTLINE makalah/laporan berbahasa Indonesia dari topik dan data yang diberikan.
        Kembalikan daftar bab, dan tiap bab berisi subbab. Untuk tiap subbab beri judul singkat dan catatan satu kalimat
        tentang isi yang akan dibahas.
        Jangan menulis isi paragraf. Jangan menambah data, angka, nama, atau sumber yang tidak ada di input.
        Judul tanpa nomor (jangan tulis "BAB I" atau "1.1"), tanpa titik di akhir.
        TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'chapters' => $schema->array()->items($schema->object([
                'title' => $schema->string()->required(),
                'sections' => $schema->array()->items($schema->object([
                    'title' => $schema->string()->required(),
                    'note' => $schema->string()->required(),
                ])->withoutAdditionalProperties())->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
