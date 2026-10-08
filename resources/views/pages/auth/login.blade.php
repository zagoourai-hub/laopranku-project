<x-layouts::auth :title="__('Masuk')" variant="login">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Masuk ke akunmu')" :description="__('Masukkan email dan kata sandimu untuk masuk')" />

        <x-form-alert :credential="true" />

        <!-- Session Status -->
        <x-auth-session-status :status="session('status')" />

        <x-passkey-verify />

        <form novalidate method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Alamat email')"
                :value="old('email')"
                type="email"
                icon="envelope"
                required
                autofocus
                autocomplete="email"
                placeholder="nama@email.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Kata sandi')"
                    type="password"
                    icon="lock-closed"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Kata sandi')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Lupa kata sandi?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Ingat saya')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" icon-trailing="arrow-right" data-test="login-button">
                    {{ __('Masuk') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Belum punya akun?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Daftar') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
