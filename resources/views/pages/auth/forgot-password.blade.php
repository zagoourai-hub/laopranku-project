<x-layouts::auth :title="__('Lupa kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Lupa kata sandi')" :description="__('Masukkan emailmu untuk menerima tautan atur ulang kata sandi')" />

        <x-form-alert />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form novalidate method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Alamat email')"
                type="email"
                required
                autofocus
                placeholder="nama@email.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Kirim tautan atur ulang kata sandi') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Atau, kembali ke') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('masuk') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
