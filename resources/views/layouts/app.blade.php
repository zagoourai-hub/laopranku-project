<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if (auth()->user()->is_guest)
            <div class="mx-auto mb-6 w-full max-w-6xl" data-test="guest-banner">
                <flux:callout icon="information-circle" variant="secondary" inline>
                    <flux:callout.heading>{{ __('Kamu masuk sebagai tamu.') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Simpan dokumenmu dengan mendaftar akun.') }}</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button size="sm" variant="primary" :href="route('register')">{{ __('Daftar akun') }}</flux:button>
                    </x-slot>
                </flux:callout>
            </div>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
