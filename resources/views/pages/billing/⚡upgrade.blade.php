<?php

use App\Services\Billing\MidtransService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Paket')] class extends Component {
    public function pay(MidtransService $midtrans): void
    {
        $user = auth()->user();

        if ($user->is_guest) {
            Flux::toast(variant: 'warning', text: __('Daftar akun dulu supaya Pro tidak hilang bersama sesi tamu.'));

            return;
        }

        if (! $midtrans->isConfigured()) {
            Flux::toast(variant: 'danger', text: __('Pembayaran belum dikonfigurasi. Hubungi admin.'));

            return;
        }

        try {
            $payment = $midtrans->createSnapTransaction($user);
        } catch (Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: __('Pembayaran belum bisa dimulai. Coba lagi sebentar lagi.'));

            return;
        }

        $this->dispatch('snap-pay', token: $payment->snap_token);
    }
};
?>

@php
    $user = auth()->user();
    $days = (int) config('plans.pro_days');
    $price = (int) config('plans.pro_price');
    $from = $user->isPro() ? $user->pro_until : now();
    $until = $from->copy()->addDays($days);
    $free = (int) config('plans.free_documents_per_month');
@endphp

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Paket') }}</flux:heading>
        <flux:subheading>{{ __('Bayar sekali untuk :days hari. Tidak ada tagihan otomatis.', ['days' => $days]) }}</flux:subheading>
    </div>

    @if ($user->is_guest)
        <flux:callout icon="information-circle" variant="warning" data-test="guest-upgrade-notice">
            <flux:callout.text>{{ __('Kamu masuk sebagai tamu. Daftar akun dulu supaya masa Pro tersimpan permanen.') }}
                <flux:link :href="route('register')">{{ __('Daftar akun') }}</flux:link></flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-white/10" data-test="plan-free">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <flux:heading size="lg">Free</flux:heading>
                    <flux:text>{{ __('Kebutuhan tugas dasar') }}</flux:text>
                </div>
                @unless ($user->isPro())
                    <flux:badge size="sm">{{ __('Paket kamu sekarang') }}</flux:badge>
                @endunless
            </div>
            <div class="text-3xl font-bold text-zinc-900 dark:text-white">Rp0</div>
            <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                <li class="flex gap-2"><flux:icon.check class="size-5 text-green-600" />{{ __(':n dokumen per bulan', ['n' => $free]) }}</li>
                <li class="flex gap-2"><flux:icon.check class="size-5 text-green-600" />{{ __('Export DOCX') }}</li>
                <li class="flex gap-2"><flux:icon.check class="size-5 text-green-600" />{{ __('Template umum') }}</li>
                <li class="flex gap-2 text-zinc-400"><flux:icon.x-mark class="size-5" />{{ __('Export PDF') }}</li>
                <li class="flex gap-2 text-zinc-400"><flux:icon.x-mark class="size-5" />{{ __('Template kampus custom') }}</li>
            </ul>
        </div>

        <div class="flex flex-col gap-4 rounded-xl border-2 border-accent p-6" data-test="plan-pro">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <flux:heading size="lg">{{ __('Pro :days hari', ['days' => $days]) }}</flux:heading>
                    <flux:text>{{ __('Akses penuh pengerjaan laporan') }}</flux:text>
                </div>
                @if ($user->isPro())
                    <flux:badge size="sm" color="green">{{ __('Aktif') }}</flux:badge>
                @endif
            </div>
            <div class="text-3xl font-bold text-zinc-900 dark:text-white">Rp{{ number_format($price, 0, ',', '.') }}
                <span class="text-sm font-normal text-zinc-500">/ {{ $days }} {{ __('hari, sekali bayar') }}</span></div>
            <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                <li class="flex gap-2"><flux:icon.check class="size-5 text-green-600" />{{ __('Dokumen tanpa batas') }}</li>
                <li class="flex gap-2"><flux:icon.check class="size-5 text-green-600" />{{ __('Export DOCX dan PDF') }}</li>
                <li class="flex gap-2"><flux:icon.check class="size-5 text-green-600" />{{ __('Template kampus custom') }}</li>
                <li class="flex gap-2"><flux:icon.check class="size-5 text-green-600" />{{ __('Generate per bab lebih banyak') }}</li>
            </ul>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 p-6 dark:border-white/10" data-test="order-summary">
        <flux:heading size="lg">{{ __('Ringkasan pesanan') }}</flux:heading>
        <div class="mt-4 max-w-md space-y-2 text-sm">
            <div class="flex justify-between gap-6"><span class="text-zinc-500">{{ __('Paket') }}</span><span class="font-medium">{{ __('Pro :days hari', ['days' => $days]) }}</span></div>
            <div class="flex justify-between gap-6"><span class="text-zinc-500">{{ __('Berlaku') }}</span><span class="font-medium">{{ $from->locale('id')->translatedFormat('j M Y') }} – {{ $until->locale('id')->translatedFormat('j M Y') }}</span></div>
            <div class="flex justify-between gap-6"><span class="text-zinc-500">{{ __('Total') }}</span><span class="text-base font-semibold">Rp{{ number_format($price, 0, ',', '.') }}</span></div>
        </div>
        <flux:text class="mt-4 max-w-xl">{{ __('Pembayaran diproses oleh Midtrans: QRIS, transfer virtual account, GoPay, ShopeePay. Masa Pro aktif setelah pembayaran terkonfirmasi. Jika kamu memperpanjang sebelum habis, sisa hari tetap dihitung.') }}</flux:text>
        <div class="mt-5 flex flex-wrap gap-2">
            <flux:button variant="primary" wire:click="pay" wire:loading.attr="disabled" :disabled="$user->is_guest" data-test="pay-pro">
                {{ __('Bayar Rp:price', ['price' => number_format($price, 0, ',', '.')]) }}
            </flux:button>
            <flux:button :href="route('settings.billing')" wire:navigate>{{ __('Riwayat pembayaran') }}</flux:button>
        </div>
    </div>

    @include('partials.snap')
</div>
