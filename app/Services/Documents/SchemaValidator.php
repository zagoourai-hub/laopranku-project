<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\File;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class SchemaValidator
{
    /**
     * Validate a document against document-v1.json plus the invariant JSON Schema cannot express (unique ids).
     *
     * @param  array<string, mixed>  $document
     * @return list<string> human-readable errors; empty when valid
     */
    public function validate(array $document): array
    {
        $data = json_decode(json_encode($document, JSON_THROW_ON_ERROR));
        $schema = json_decode(File::get(resource_path('schemas/document-v1.json')));

        $result = (new Validator)->validate($data, $schema);

        $errors = [];

        if (! $result->isValid()) {
            foreach ((new ErrorFormatter)->formatKeyed($result->error()) as $path => $messages) {
                foreach ($messages as $message) {
                    $errors[] = ($path === '' ? '/' : $path).': '.$message;
                }
            }

            return $errors;
        }

        return $this->duplicateIdErrors($document);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function duplicateIdErrors(array $document): array
    {
        $ids = [];

        array_walk_recursive($document, function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id') {
                $ids[] = $value;
            }
        });

        return array_map(
            fn (int|string $id): string => "/: id [{$id}] is used more than once",
            array_keys(array_filter(array_count_values($ids), fn (int $count): bool => $count > 1)),
        );
    }
}
