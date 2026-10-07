@props([
    'credential' => false,
    'title' => null,
])

@php
    $messages = collect($errors->all())->unique()->values();

    $throttlePrefix = \Illuminate\Support\Str::before(trans('auth.throttle'), ':seconds');
    $isCredentialFailure = $credential && collect($errors->get('email'))->contains(
        fn (string $message): bool => $message === trans('auth.failed') || ($throttlePrefix !== '' && str_starts_with($message, $throttlePrefix))
    );

    $heading = $title ?? ($isCredentialFailure ? __('Gagal masuk') : __('Periksa kembali isian Anda'));
@endphp

@if ($messages->isNotEmpty())
    <div
        x-data="{ open: true }"
        x-show="open"
        x-init="$nextTick(() => $el.focus())"
        role="alert"
        tabindex="-1"
        data-test="form-alert"
        {{ $attributes->class('alert-in flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900 outline-none focus-visible:ring-2 focus-visible:ring-accent dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100') }}
    >
        <flux:icon.exclamation-circle variant="mini" class="mt-0.5 size-5 shrink-0 text-red-700 dark:text-red-400" />

        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">{{ $heading }}</p>
            @if ($messages->count() === 1)
                <p class="mt-1 text-sm">{{ $messages->first() }}</p>
            @else
                <ul class="mt-1 list-disc space-y-0.5 ps-4 text-sm">
                    @foreach ($messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <button
            type="button"
            x-on:click="open = false"
            class="-m-1 h-8 w-8 shrink-0 rounded-lg p-1 text-red-700 transition-colors duration-150 hover:bg-red-100 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none dark:text-red-300 dark:hover:bg-red-500/20"
            aria-label="{{ __('Tutup pesan') }}"
        >
            <flux:icon.x-mark variant="mini" class="mx-auto size-4" />
        </button>
    </div>
@endif
