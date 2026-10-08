<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-zinc-950">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <x-appearance-toggle class="max-lg:hidden" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Ruang kerja')" class="grid">
                    <flux:sidebar.item icon="document-text" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dokumen') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Contoh: kuota Free belum terhubung ke data pengguna. --}}
            <div class="mx-2 mb-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
                <div class="flex items-center justify-between text-sm font-medium text-zinc-900 dark:text-white">
                    <span>{{ __('Paket Free') }}</span>
                    <span class="tabular-nums text-zinc-500">2 / 3</span>
                </div>
                <p class="mt-1 text-sm text-zinc-500">{{ __('2 dari 3 dokumen bulan ini') }}</p>
                <div class="mt-3 h-1.5 rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="3" aria-valuenow="2">
                    <div class="h-full w-2/3 rounded-full bg-accent"></div>
                </div>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-appearance-toggle class="me-1" />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        @if (auth()->user()->is_guest)
            <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-2 text-sm text-zinc-700 dark:border-white/10 dark:bg-zinc-950 dark:text-zinc-300" data-test="guest-banner">
                {{ __('Kamu masuk sebagai tamu.') }}
                <a href="{{ route('register') }}" class="font-medium text-accent underline">{{ __('Simpan dokumenmu, daftar akun') }}</a>
            </div>
        @endif

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
