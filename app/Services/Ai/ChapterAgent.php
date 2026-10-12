<?php

namespace App\Services\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Writes the body paragraphs of one chapter, section by section.
 */
#[Temperature(0.5)]
#[Timeout(180)]
class ChapterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TXT'
        Kamu menulis isi bab makalah/laporan berbahasa Indonesia yang formal dan jelas.
        Untuk setiap subbab yang diminta, tulis paragraf isi sesuai judulnya dan catatannya, dengan gaya yang diminta.
        Aturan sumber (wajib):
        - Jangan mengarang sumber, penulis, tahun, judul buku, jurnal, URL, atau kutipan.
        - Hanya boleh mengutip atau merujuk sumber yang ada di daftar SUMBER pada pesan. Jika daftar itu kosong, jangan menulis kutipan atau sitasi apa pun.
        - Jangan menambah angka, data hasil, atau nama orang yang tidak ada di input.
        Jawab dengan index subbab sesuai daftar dan paragraf-paragraf isinya.
        TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sections' => $schema->array()->items($schema->object([
                'index' => $schema->integer()->required(),
                'paragraphs' => $schema->array()->items($schema->string())->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
