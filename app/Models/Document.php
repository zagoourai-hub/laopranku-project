<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property DocumentType $type
 * @property EducationLevel $education_level
 * @property DocumentStatus $status
 * @property array<string, mixed>|null $content
 * @property string $template_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['user_id', 'title', 'type', 'education_level', 'status', 'content', 'template_key'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'education_level' => EducationLevel::class,
            'status' => DocumentStatus::class,
            'content' => 'array',
        ];
    }

    /**
     * Signed DOCX/PDF download link, valid for 10 minutes. Create it on click, not on render.
     */
    public function downloadUrl(string $format = 'docx', bool $inline = false): string
    {
        return URL::temporarySignedRoute('documents.download', now()->addMinutes(10), ['document' => $this, 'format' => $format] + ($inline ? ['inline' => 1] : []));
    }

    /**
     * Copy as a fresh draft for the same user. Every content id is regenerated so ids stay unique per document.
     */
    public function duplicate(): self
    {
        $reid = function (mixed $node) use (&$reid): mixed {
            if (! is_array($node)) {
                return $node;
            }

            foreach ($node as $key => $value) {
                $node[$key] = $key === 'id' && is_string($value) ? (string) Str::uuid() : $reid($value);
            }

            return $node;
        };

        return static::create([
            'user_id' => $this->user_id,
            'title' => $this->title.' (Salinan)',
            'type' => $this->type,
            'education_level' => $this->education_level,
            'status' => DocumentStatus::Draft,
            'content' => $reid($this->content),
            'template_key' => $this->template_key,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DocumentJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(DocumentJob::class);
    }
}
