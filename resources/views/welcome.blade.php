<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Perapi makalah mahasiswa')])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <header class="border-b border-zinc-200 dark:border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <x-app-logo href="{{ route('home') }}" />
                <nav class="flex items-center gap-2">
                    <x-appearance-toggle />
                    @auth
                        <flux:button :href="route('dashboard')" size="sm">{{ __('Dokumen') }}</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Masuk') }}</flux:button>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" size="sm">{{ __('Coba gratis') }}</flux:button>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-6xl gap-12 px-4 py-16 lg:grid-cols-2 lg:items-center lg:py-24">
                <div class="flex flex-col gap-6">
                    <p class="w-fit rounded-md bg-zinc-100 px-2.5 py-1 text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ __('Makalah · Laporan praktikum · Laporan PKL · Proposal') }}</p>
                    <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ __('Kamu tulis isinya. Formatnya kami yang rapikan.') }}</h1>
                    <p class="max-w-xl text-lg text-zinc-600 dark:text-zinc-400">{{ __('Berhenti mengatur spasi rusak, margin lari, daftar isi manual, dan nomor romawi di Word. Rapi menyusun naskah akademik siap cetak dalam hitungan detik.') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <flux:button :href="Route::has('register') ? route('register') : route('login')" variant="primary">{{ __('Rapikan makalah') }}</flux:button>
                    </div>
                    <p class="font-mono text-xs text-zinc-500">{{ __('TNR 12 · spasi 1.5 · margin 4-3-3-3 · siap dibuka di Word') }}</p>
                </div>

                <div class="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-white/10 dark:bg-zinc-950">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-mono uppercase tracking-wide text-zinc-500">{{ __('Teks mentah (paste)') }}</span>
                            <span class="text-accent-content">{{ __('Format acak') }}</span>
                        </div>
                        <p class="mt-3 font-mono text-sm text-zinc-700 dark:text-zinc-300">1. latar belakang masalah</p>
                        <p class="mt-2 font-mono text-xs leading-relaxed text-zinc-500">Perkembangan teknologi sangat pesat.. banyak mahasiswa kesulitan mengatur margin dan spasi sesuai pedoman dikti...</p>
                        <p class="mt-3 font-mono text-xs text-zinc-500">Font: Calibri 11 · Spasi: acak</p>
                    </div>

                    <div class="paper mx-auto aspect-[1/1.414] w-full max-w-xs rounded-md sm:max-w-none border border-zinc-300 p-6 text-center shadow-sm">
                        <p class="text-[10px] tracking-widest">MAKALAH</p>
                        <p class="mt-3 text-sm font-bold leading-tight">ANALISIS SISTEM CERDAS PADA MANAJEMEN SITASI</p>
                        <div class="mx-auto mt-6 grid h-10 w-16 place-items-center border border-dashed border-zinc-400 text-[8px] text-zinc-500">LOGO</div>
                        <p class="mt-8 text-xs font-bold">BUDI SANTOSO</p>
                        <p class="text-[10px]">NIM 2201234567</p>
                        <p class="mt-3 text-xs font-bold">UNIVERSITAS PADJADJARAN</p>
                        <p class="text-[10px]">2026</p>
                    </div>
                </div>
            </section>

            <section class="border-t border-zinc-200 dark:border-white/10">
                <div class="mx-auto max-w-6xl px-4 py-16">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ __('Yang biasanya bikin ribet di Word') }}</h2>
                    <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('Kendala umum naskah manual dan cara Rapi menanganinya.') }}</p>
                    <div class="mt-8 overflow-x-auto rounded-xl border border-zinc-200 dark:border-white/10">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-950">
                                <tr><th class="px-4 py-3 font-medium">{{ __('Di Word') }}</th><th class="px-4 py-3 font-medium">{{ __('Di Rapi') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-white/10">
                                @foreach ([
                                    ['Format rusak setelah copy-paste', 'Konten dibersihkan lalu dirender ulang'],
                                    ['Nomor bab diketik manual', 'BAB I, 1.1, 1.2 otomatis'],
                                    ['Daftar isi manual', 'Field daftar isi asli Word'],
                                    ['Halaman romawi lalu angka', 'Section break otomatis'],
                                    ['Margin dan font diatur ulang', 'Template umum 4-3-3-3'],
                                    ['Daftar pustaka berantakan', 'Hanging indent dan urutan alfabet'],
                                ] as [$word, $rapi])
                                    <tr><td class="px-4 py-3 text-zinc-500">{{ $word }}</td><td class="px-4 py-3 font-medium">{{ $rapi }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="cara-kerja" class="border-t border-zinc-200 dark:border-white/10">
                <div class="mx-auto max-w-6xl px-4 py-16">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ __('Cara kerja') }}</h2>
                    <ol class="mt-8 grid gap-8 lg:grid-cols-3">
                        @foreach ([
                            ['Tempel teks atau tulis topik', 'Masukkan naskah mentah, ringkasan riset, atau cukup judul topik tugasmu.'],
                            ['Tinjau struktur bab', 'Ubah, tambah, atau urutkan bab sebelum isi ditulis.'],
                            ['Unduh DOCX atau PDF', 'Dapatkan file Word murni dengan field daftar isi dan margin baku siap cetak.'],
                        ] as $i => [$title, $text])
                            <li class="flex flex-col gap-2 border-t-2 border-zinc-900 pt-4 dark:border-white">
                                <span class="font-mono text-sm text-accent-content">{{ sprintf('%02d', $i + 1) }}</span>
                                <h3 class="text-lg font-semibold">{{ $title }}</h3>
                                <p class="text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section class="border-t border-zinc-200 dark:border-white/10">
                <div class="mx-auto flex max-w-6xl flex-col items-start gap-4 px-4 py-16">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ __('Siap merapikan tugas kuliahmu?') }}</h2>
                    <p class="text-zinc-600 dark:text-zinc-400">{{ __('Mulai gratis tanpa kartu kredit.') }}</p>
                    <flux:button :href="Route::has('register') ? route('register') : route('login')" variant="primary">{{ __('Coba gratis sekarang') }}</flux:button>
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-200 dark:border-white/10">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-6 text-sm text-zinc-500">
                <x-app-logo href="{{ route('home') }}" class="text-base" />
                <span>© {{ date('Y') }} Rapi</span>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
