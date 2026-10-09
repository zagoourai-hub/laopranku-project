{{-- Midtrans Snap popup. Pro is activated only by the webhook; callbacks here just refresh the page. --}}
@assets
<script src="{{ app(\App\Services\Billing\MidtransService::class)->snapJsUrl() }}" data-client-key="{{ config('midtrans.client_key') }}"></script>
@endassets

<div
    x-data
    x-on:snap-pay.window="window.snap ? window.snap.pay($event.detail.token, {
        onSuccess: () => window.location.reload(),
        onPending: () => window.location.reload(),
        onError: () => window.location.reload(),
    }) : alert(@js(__('Popup pembayaran belum siap. Muat ulang halaman lalu coba lagi.')))"
></div>
