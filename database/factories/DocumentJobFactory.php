<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentJob>
 */
class DocumentJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'chapter_index' => 0,
            'status' => 'pending',
        ];
    }
}
