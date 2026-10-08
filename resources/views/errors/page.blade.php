@php
    // Error pages must render even when the DB or session is down, so never let the auth check throw.
    try {
        $signedIn = auth()->check();
    } catch (\Throwable) {
        $signedIn = false;
    }
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-6 px-4 py-12 text-center">
            <a href="{{ url('/') }}" class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">laporanku<span class="text-accent">.</span></a>

            <div class="flex w-full flex-col items-center gap-3 rounded-xl border border-zinc-200 bg-white p-8 dark:border-white/10 dark:bg-zinc-900">
                <p class="text-sm font-semibold tabular-nums text-accent">{{ $code }}</p>
                <h1 class="text-2xl font-semibold tracking-tight text-balance">{{ $title }}</h1>
                <p class="text-zinc-600 dark:text-zinc-400">{{ $message }}</p>

                <flux:button :href="$signedIn ? url('/dashboard') : url('/')" variant="primary" class="mt-3" data-test="error-back">
                    {{ $signedIn ? __('Kembali ke Dokumen') : __('Kembali ke Beranda') }}
                </flux:button>
            </div>
        </main>

        @fluxScripts
    </body>
</html>
