<x-layouts::auth :title="__('Konfirmasi kata sandi')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Konfirmasi kata sandi')"
            :description="__('Ini area aman aplikasi. Konfirmasi kata sandimu sebelum melanjutkan.')"
        />

        <x-form-alert />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('Konfirmasi dengan passkey')"
            :loading-label="__('Mengonfirmasi...')"
            :separator="__('Atau konfirmasi dengan kata sandi')"
        />

        <form novalidate method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Kata sandi')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Kata sandi')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('Konfirmasi') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
