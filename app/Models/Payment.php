<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $order_id
 * @property int $gross_amount
 * @property string $status pending|settlement|capture|expire|cancel|deny|failure
 * @property string|null $payment_type
 * @property string|null $transaction_id
 * @property string|null $snap_token
 * @property array<string, mixed>|null $raw_notification
 * @property Carbon|null $paid_at
 */
#[Fillable(['user_id', 'order_id', 'gross_amount', 'status', 'payment_type', 'transaction_id', 'snap_token', 'raw_notification', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'raw_notification' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isPaid() => 'Berhasil',
            $this->status === 'pending' => 'Menunggu',
            $this->status === 'expire' => 'Kedaluwarsa',
            default => 'Gagal',
        };
    }

    public function statusColor(): string
    {
        return match ($this->statusLabel()) {
            'Berhasil' => 'green',
            'Menunggu' => 'amber',
            default => 'red',
        };
    }
}
