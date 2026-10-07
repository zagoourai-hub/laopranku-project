<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'type' => DocumentType::Makalah,
            'status' => DocumentStatus::Draft,
            'content' => null,
            'template_key' => 'default',
        ];
    }

    /**
     * Indicate that the document is finished and ready to export.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => ['status' => DocumentStatus::Ready]);
    }
}
