<?php

namespace App\Models;

use Database\Factories\DocumentJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property int|null $chapter_index
 * @property string $status
 * @property string|null $error
 * @property int $tokens_in
 * @property int $tokens_out
 * @property string $cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['document_id', 'chapter_index', 'status', 'error', 'tokens_in', 'tokens_out', 'cost'])]
class DocumentJob extends Model
{
    /** @use HasFactory<DocumentJobFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
