<x-layouts::auth :title="__('Daftar')" variant="register">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Buat akun')" :description="__('Isi data di bawah untuk membuat akunmu')" />

        <x-form-alert />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form novalidate method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" x-data="{ pw: '', get strength() { return [this.pw.length >= 8, /[a-z]/.test(this.pw) && /[A-Z]/.test(this.pw), /\d/.test(this.pw), /[^A-Za-z0-9]/.test(this.pw)].filter(Boolean).length } }">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Nama')"
                :value="old('name')"
                type="text"
                icon="user"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Nama lengkap')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Alamat email')"
                :value="old('email')"
                type="email"
                icon="envelope"
                required
                autocomplete="email"
                placeholder="nama@email.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Kata sandi')"
                type="password"
                icon="lock-closed"
                x-model="pw"
                required
                autocomplete="new-password"
                :placeholder="__('Kata sandi')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="-mt-3 flex flex-col gap-1.5" aria-live="polite">
                <div class="grid grid-cols-4 gap-1.5" aria-hidden="true">
                    <template x-for="n in 4" :key="n">
                        <span class="h-1.5 rounded-full bg-zinc-200 transition-colors duration-200 dark:bg-white/10" :class="n <= strength && '!bg-accent'"></span>
                    </template>
                </div>
                <p class="text-xs text-zinc-500" x-text="pw === '' ? '{{ __('Minimal 8 karakter, campur huruf besar, kecil, angka, dan simbol.') }}' : ['{{ __('Lemah') }}', '{{ __('Lemah') }}', '{{ __('Cukup') }}', '{{ __('Kuat') }}', '{{ __('Sangat kuat') }}'][strength]"></p>
            </div>

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Konfirmasi kata sandi')"
                type="password"
                icon="lock-closed"
                required
                autocomplete="new-password"
                :placeholder="__('Konfirmasi kata sandi')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" icon-trailing="arrow-right" data-test="register-user-button">
                    {{ __('Buat akun') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Sudah punya akun?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Masuk') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
