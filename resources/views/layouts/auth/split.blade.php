<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside class="relative hidden flex-col justify-between bg-zinc-950 p-10 text-zinc-100 lg:flex">
                <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight" wire:navigate>rapi<span class="text-red-500">.</span></a>

                <div class="flex flex-col gap-8">
                    <div class="flex flex-col gap-3">
                        <p class="max-w-sm text-3xl font-semibold tracking-tight text-balance">{{ __('Kamu tulis isinya. Formatnya kami yang rapikan.') }}</p>
                        <p class="max-w-sm text-zinc-400">{{ __('Naskah akademik siap cetak: bab bernomor, daftar isi, dan halaman romawi diatur otomatis.') }}</p>
                    </div>

                    <div class="paper w-44 rotate-0 rounded-sm p-5 text-center shadow-sm" aria-hidden="true">
                        <p class="text-[8px] tracking-widest">MAKALAH</p>
                        <p class="mt-2 text-[10px] font-bold leading-tight">ANALISIS SISTEM CERDAS PADA MANAJEMEN SITASI</p>
                        <div class="mx-auto mt-4 grid h-6 w-10 place-items-center border border-dashed border-zinc-400 text-[6px] text-zinc-500">LOGO</div>
                        <p class="mt-5 text-[8px] font-bold">BUDI SANTOSO</p>
                        <p class="text-[7px]">NIM 2201234567</p>
                    </div>
                </div>

                <p class="font-mono text-xs text-zinc-500">{{ __('TNR 12 · spasi 1.5 · margin 4-3-3-3') }}</p>
            </aside>

            <main class="flex flex-col px-6 py-8 sm:px-10">
                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white lg:invisible" wire:navigate>rapi<span class="text-accent">.</span></a>
                    <x-appearance-toggle />
                </div>

                <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-6 py-10">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
