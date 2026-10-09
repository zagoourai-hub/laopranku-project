<?php

namespace App\Mcp\Tools;

use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Daftar template format (font, margin, spasi), jenis dokumen, dan jenjang yang tersedia.')]
#[IsReadOnly]
class ListTemplates extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::json([
            'templates' => array_map(fn ($key, $t) => ['key' => $key] + $t, array_keys(config('documents.templates')), config('documents.templates')),
            'types' => collect(DocumentType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values(),
            'education_levels' => collect(EducationLevel::cases())->map(fn ($l) => ['value' => $l->value, 'label' => $l->label()])->values(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
