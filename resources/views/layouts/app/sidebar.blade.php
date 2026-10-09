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

            @php
                $plans = app(\App\Services\Billing\PlanLimits::class);
                $quota = $plans->quotaSummary(auth()->user());
            @endphp
            <div class="mx-2 mb-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900" data-test="quota-card">
                @if ($quota['isPro'])
                    <div class="flex items-center justify-between text-sm font-medium text-zinc-900 dark:text-white">
                        <span>{{ __('Paket Pro') }}</span>
                        <flux:badge size="sm" color="green">{{ __('Aktif') }}</flux:badge>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500" data-test="pro-until">{{ __('Pro aktif sampai :date', ['date' => $quota['proUntil']->locale('id')->translatedFormat('j F Y')]) }}</p>
                @else
                    <div class="flex items-center justify-between text-sm font-medium text-zinc-900 dark:text-white">
                        <span>{{ __('Paket Free') }}</span>
                        <span class="tabular-nums text-zinc-500">{{ $quota['used'] }} / {{ $quota['limit'] }}</span>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500">{{ __(':used dari :limit dokumen bulan ini', ['used' => $quota['used'], 'limit' => $quota['limit']]) }}</p>
                    <div class="mt-3 h-1.5 rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $quota['limit'] }}" aria-valuenow="{{ min($quota['used'], $quota['limit']) }}">
                        <div class="h-full rounded-full bg-accent" style="width: {{ min(100, round($quota['used'] / max(1, $quota['limit']) * 100)) }}%"></div>
                    </div>
                    <flux:button size="sm" variant="primary" class="mt-3 w-full" :href="$plans->upgradeUrl()" data-test="upgrade-cta-sidebar">{{ __('Upgrade ke Pro') }}</flux:button>
                @endif
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

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
