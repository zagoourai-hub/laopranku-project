@props([
    'title' => null,
    'variant' => 'single',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="flex min-h-dvh flex-col">
            <header class="border-b border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <a href="{{ route('home') }}" class="flex items-center gap-2" wire:navigate>
                        <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">rapi<span class="text-accent">.</span></span>
                        <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ __('Akademik') }}</span>
                    </a>
                    <nav class="flex items-center gap-1 text-sm sm:gap-3">
                        <a href="{{ route('home') }}#cara-kerja" class="hidden items-center gap-1.5 rounded-lg px-2 py-1.5 text-zinc-600 transition-colors duration-150 hover:text-zinc-900 sm:flex dark:text-zinc-400 dark:hover:text-white">
                            <flux:icon.question-mark-circle variant="mini" class="size-4" />{{ __('Cara kerja') }}
                        </a>
                        <a href="{{ route('home') }}" class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-zinc-600 transition-colors duration-150 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white" wire:navigate>
                            <flux:icon.arrow-left variant="mini" class="size-4" /><span class="max-sm:hidden">{{ __('Kembali ke Beranda') }}</span><span class="sm:hidden">{{ __('Beranda') }}</span>
                        </a>
                        <x-appearance-toggle />
                    </nav>
                </div>
            </header>

            <main class="mx-auto flex w-full flex-1 flex-col items-center gap-6 px-4 py-8 sm:px-6 sm:py-12 {{ $variant === 'single' ? 'max-w-md' : 'max-w-6xl' }}">
                @php
                    $benefits = [
                        ['Format otomatis', 'Times New Roman 12, spasi 1.5, margin 4-3-3-3.'],
                        ['Isi tulisanmu tetap', 'Di jalur tempel dan upload, kalimatmu tidak diubah AI.'],
                        ['DOCX dan PDF', 'File Word asli yang bisa diedit, plus PDF.'],
                    ];
                @endphp

                @if ($variant === 'login')
                    <div class="sheet-in grid w-full max-w-4xl overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-[0_1px_2px_rgb(24_24_27/0.04),0_24px_48px_-24px_rgb(24_24_27/0.2)] lg:grid-cols-2 dark:border-white/10 dark:bg-zinc-900 dark:shadow-none">
                        <aside class="hidden flex-col gap-6 bg-rose-50 p-10 lg:flex dark:bg-white/[0.03]">
                            <span class="w-fit rounded-md bg-white px-2.5 py-1 text-xs font-semibold tracking-wide text-zinc-700 uppercase dark:bg-white/10 dark:text-zinc-200">{{ __('Ruang kerja pelajar dan mahasiswa') }}</span>
                            <h2 class="text-3xl font-semibold tracking-tight text-balance text-zinc-900 dark:text-white">{{ __('Format makalah rapi tanpa repot margin.') }}</h2>
                            <p class="text-zinc-600 dark:text-zinc-400">{{ __('Margin 4-3-3-3, nomor halaman, dan daftar isi diatur otomatis. Ekspor ke DOCX dan PDF.') }}</p>

                            <div class="mt-auto -rotate-1 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-zinc-900" aria-hidden="true">
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="font-mono text-zinc-600 dark:text-zinc-400">Laporan_Akhir.docx</span>
                                    <span class="rounded bg-rose-50 px-2 py-0.5 font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-300">A4</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    <div class="h-2 w-2/5 rounded bg-zinc-300 dark:bg-white/20"></div>
                                    <div class="h-1.5 w-full rounded bg-zinc-200 dark:bg-white/10"></div>
                                    <div class="h-1.5 w-11/12 rounded bg-zinc-200 dark:bg-white/10"></div>
                                    <div class="h-1.5 w-3/4 rounded bg-zinc-200 dark:bg-white/10"></div>
                                </div>
                                <div class="mt-4 rounded-lg bg-zinc-50 px-3 py-2 font-mono text-xs text-zinc-600 dark:bg-white/5 dark:text-zinc-400">{{ __('TNR 12 · spasi 1.5 · margin 4-3-3-3') }}</div>
                            </div>
                        </aside>

                        <section class="auth-sheet flex flex-col justify-center p-6 sm:p-10">
                            {{ $slot }}
                        </section>
                    </div>
                @elseif ($variant === 'register')
                    <div class="grid w-full gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                        <section class="auth-sheet sheet-in overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-[0_1px_2px_rgb(24_24_27/0.04),0_24px_48px_-24px_rgb(24_24_27/0.2)] dark:border-white/10 dark:bg-zinc-900 dark:shadow-none">
                            <div class="h-1 bg-accent"></div>
                            <div class="p-6 sm:p-10">
                                {{ $slot }}
                            </div>
                        </section>

                        <aside class="flex flex-col gap-4 rounded-3xl border border-zinc-200 bg-rose-50 p-6 sm:p-8 dark:border-white/10 dark:bg-white/[0.03]">
                            <div class="paper mx-auto aspect-[1/1.414] w-full max-w-[13rem] rounded-sm border border-zinc-300 p-5 text-center shadow-sm" aria-hidden="true">
                                <p class="text-[8px] tracking-widest">MAKALAH</p>
                                <p class="mt-2 text-[10px] font-bold leading-tight">ANALISIS SISTEM CERDAS PADA MANAJEMEN SITASI</p>
                                <div class="mx-auto mt-4 grid h-6 w-10 place-items-center border border-dashed border-zinc-400 text-[6px] text-zinc-500">LOGO</div>
                                <p class="mt-5 text-[8px] font-bold">BUDI SANTOSO</p>
                                <p class="text-[7px]">NIM 2201234567</p>
                            </div>
                            <p class="text-center font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ __('A4 · margin 4-3-3-3 · TNR 12 · spasi 1.5') }}</p>
                            <ul class="mt-2 flex flex-col gap-3">
                                @foreach ($benefits as [$heading, $text])
                                    <li class="rounded-xl bg-white p-4 dark:bg-white/5">
                                        <p class="font-medium text-zinc-900 dark:text-white">{{ __($heading) }}</p>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __($text) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </aside>
                    </div>
                @else
                    <section class="auth-sheet sheet-in w-full overflow-hidden rounded-3xl border border-zinc-200 bg-white p-6 shadow-[0_1px_2px_rgb(24_24_27/0.04),0_24px_48px_-24px_rgb(24_24_27/0.2)] sm:p-10 dark:border-white/10 dark:bg-zinc-900 dark:shadow-none">
                        {{ $slot }}
                    </section>
                @endif

                @if ($variant === 'login')
                    <div class="grid w-full max-w-4xl gap-4 sm:grid-cols-2">
                        @foreach (array_slice($benefits, 1) as [$heading, $text])
                            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-zinc-900">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ __($heading) }}</p>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __($text) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </main>

            <footer class="border-t border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-5 text-sm text-zinc-500 sm:px-6">
                    <span>{{ __('Standar format makalah dan laporan akademik untuk sekolah dan kampus di Indonesia.') }}</span>
                    <span>© {{ date('Y') }} Laporanku</span>
                </div>
            </footer>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <script>
            // Hubungkan pesan error inline ke input-nya untuk pembaca layar.
            (() => {
                const link = () => document.querySelectorAll('.auth-sheet [data-flux-error]').forEach((error, index) => {
                    if (! error.textContent.trim()) return;
                    error.id ||= 'field-error-' + index;
                    error.parentElement?.querySelector('input, textarea, select')?.setAttribute('aria-describedby', error.id);
                });
                link();
                document.addEventListener('livewire:navigated', link);
            })();
        </script>

        @fluxScripts
    </body>
</html>
