<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Midtrans Snap over plain REST (no SDK): Snap token, signature check, status re-check, idempotent Pro activation.
 */
class MidtransService
{
    public function isConfigured(): bool
    {
        return filled(config('midtrans.server_key')) && filled(config('midtrans.client_key'));
    }

    public function snapJsUrl(): string
    {
        return $this->host('app').'/snap/snap.js';
    }

    /**
     * Create a pending Payment with a Snap token for one Pro period.
     */
    public function createSnapTransaction(User $user): Payment
    {
        $orderId = 'LPK-'.$user->id.'-'.Str::ulid();
        $amount = (int) config('plans.pro_price');
        $days = (int) config('plans.pro_days');

        $response = $this->client()->post($this->host('app').'/snap/v1/transactions', [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'item_details' => [['id' => 'pro', 'price' => $amount, 'quantity' => 1, 'name' => "Laporanku Pro {$days} hari"]],
            'customer_details' => ['first_name' => $user->name, 'email' => $user->email],
        ]);

        if ($response->failed() || blank($response->json('token'))) {
            throw new RuntimeException('Midtrans Snap gagal: HTTP '.$response->status());
        }

        return Payment::create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'gross_amount' => $amount,
            'status' => 'pending',
            'snap_token' => $response->json('token'),
        ]);
    }

    /**
     * signature_key = sha512(order_id + status_code + gross_amount + server_key), as sent in the notification.
     *
     * @param  array<string, mixed>  $notification
     */
    public function verifySignature(array $notification): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! is_string($notification[$key] ?? null)) {
                return false;
            }
        }

        $expected = hash('sha512', $notification['order_id'].$notification['status_code'].$notification['gross_amount'].config('midtrans.server_key'));

        return filled(config('midtrans.server_key'))
            && hash_equals($expected, $notification['signature_key']);
    }

    /**
     * Authoritative status straight from the Midtrans API.
     *
     * @return array<string, mixed>
     */
    public function fetchStatus(string $orderId): array
    {
        $response = $this->client()->get($this->host('api').'/v2/'.urlencode($orderId).'/status');

        if ($response->failed()) {
            throw new RuntimeException('Midtrans status gagal: HTTP '.$response->status());
        }

        return $response->json();
    }

    /**
     * Apply a (signature-verified) notification. Status and amount come from the API re-check, never the webhook body.
     * Idempotent: a paid Payment never extends Pro twice and never goes back to unpaid.
     */
    public function applyNotification(string $orderId): ?Payment
    {
        if (! Payment::where('order_id', $orderId)->exists()) {
            return null;
        }

        $remote = $this->fetchStatus($orderId);

        return DB::transaction(function () use ($orderId, $remote): Payment {
            $payment = Payment::where('order_id', $orderId)->lockForUpdate()->firstOrFail();

            if ($payment->isPaid() || (int) round((float) ($remote['gross_amount'] ?? 0)) !== $payment->gross_amount) {
                return $payment;
            }

            $status = (string) ($remote['transaction_status'] ?? $payment->status);
            $paid = $status === 'settlement'
                || ($status === 'capture' && ($remote['fraud_status'] ?? 'accept') === 'accept');

            $payment->fill([
                'status' => $status,
                'payment_type' => $remote['payment_type'] ?? $payment->payment_type,
                'transaction_id' => $remote['transaction_id'] ?? $payment->transaction_id,
                'raw_notification' => $remote,
                'paid_at' => $paid ? now() : null,
            ])->save();

            if ($paid) {
                User::whereKey($payment->user_id)->lockForUpdate()->firstOrFail()->extendPro((int) config('plans.pro_days'));
            }

            return $payment;
        });
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth((string) config('midtrans.server_key'), '')->acceptJson()->timeout(20);
    }

    private function host(string $kind): string
    {
        return 'https://'.$kind.(config('midtrans.is_production') ? '' : '.sandbox').'.midtrans.com';
    }
}
