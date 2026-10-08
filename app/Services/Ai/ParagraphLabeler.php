<?php

namespace App\Services\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Labels paragraphs by id. It never returns text, so the document is always built from the user's own words.
 */
#[Temperature(0)]
#[Timeout(120)]
class ParagraphLabeler implements Agent, HasStructuredOutput
{
    use Promptable;

    public const LABELS = ['chapter', 'section', 'paragraph', 'bullet', 'numbered', 'drop'];

    public function instructions(): string
    {
        return <<<'TXT'
        Kamu memberi label struktur pada paragraf makalah/laporan berbahasa Indonesia. Jangan menulis ulang teks.
        Untuk SETIAP id paragraf yang diberikan, kembalikan tepat satu label:
        - chapter: judul bab (tingkat 1), mis. "Pendahuluan", "BAB II Tinjauan Pustaka".
        - section: judul subbab (tingkat 2), mis. "Latar Belakang", "1.2 Rumusan Masalah".
        - paragraph: paragraf isi biasa.
        - bullet: butir daftar tanpa nomor.
        - numbered: butir daftar bernomor.
        - drop: hanya untuk sisa nomor/penanda tanpa isi (mis. "I.", "-", nomor halaman).
        Judul biasanya pendek, tanpa titik di akhir, dan diikuti paragraf isi. Jika ragu, pakai paragraph.
        TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'labels' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required(),
                'label' => $schema->string()->enum(self::LABELS)->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
