<?php

use App\Models\Payment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tagihan')] class extends Component {
    #[Computed]
    public function payments()
    {
        return Payment::where('user_id', auth()->id())->latest()->limit(20)->get();
    }

    public function resume(int $id): void
    {
        $payment = Payment::where('user_id', auth()->id())->where('status', 'pending')->findOrFail($id);

        $this->dispatch('snap-pay', token: $payment->snap_token);
    }
};
?>

<section class="mx-auto w-full max-w-6xl">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Tagihan') }}</flux:heading>

    @php($user = auth()->user())

    <x-pages::settings.layout :heading="__('Tagihan')" :subheading="__('Masa aktif Pro dan riwayat pembayaran')">
        <div class="flex flex-col gap-6" data-test="billing-status">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-white/10">
                @if ($user->isPro())
                    <flux:badge color="green" size="sm">{{ __('Pro aktif') }}</flux:badge>
                    <flux:heading class="mt-2">{{ __('Berlaku sampai :date', ['date' => $user->pro_until->locale('id')->translatedFormat('j M Y')]) }}</flux:heading>
                    <flux:text>{{ __('Perpanjangan ditambahkan setelah tanggal berakhir saat ini.') }}</flux:text>
                @else
                    <flux:badge size="sm">{{ __('Paket Free') }}</flux:badge>
                    <flux:text class="mt-2">{{ __('Upgrade ke Pro untuk dokumen tanpa batas dan export PDF.') }}</flux:text>
                @endif
                <flux:button class="mt-4" variant="primary" :icon="$user->isPro() ? 'arrow-path' : 'arrow-up-circle'" :href="route('billing.upgrade')" wire:navigate>
                    {{ $user->isPro() ? __('Perpanjang :n hari', ['n' => config('plans.pro_days')]) : __('Upgrade ke Pro') }}
                </flux:button>
            </div>

            <div>
                <flux:heading>{{ __('Riwayat pembayaran') }}</flux:heading>
                @forelse ($this->payments as $payment)
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 py-3 last:border-0 dark:border-white/10" wire:key="payment-{{ $payment->id }}" data-test="payment-row">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $payment->order_id }}</div>
                            <div class="text-sm text-zinc-500">
                                {{ $payment->created_at->locale('id')->translatedFormat('j M Y') }} · Rp{{ number_format($payment->gross_amount, 0, ',', '.') }}@if ($payment->payment_type) · {{ str_replace('_', ' ', $payment->payment_type) }}@endif
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <flux:badge size="sm" :color="$payment->statusColor()">{{ $payment->statusLabel() }}</flux:badge>
                            @if ($payment->status === 'pending' && $payment->snap_token)
                                <flux:button size="sm" wire:click="resume({{ $payment->id }})" data-test="resume-payment">{{ __('Bayar') }}</flux:button>
                            @endif
                        </div>
                    </div>
                @empty
                    <flux:text class="mt-2" data-test="no-payments">{{ __('Belum ada pembayaran.') }}</flux:text>
                @endforelse
                <flux:text class="mt-4 text-sm">{{ __('Pembayaran diproses oleh Midtrans. Hubungi bantuan jika dana terpotong tetapi status belum berubah.') }}</flux:text>
            </div>
        </div>
    </x-pages::settings.layout>

    @include('partials.snap')
</section>
