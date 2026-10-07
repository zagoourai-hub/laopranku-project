<x-layouts::auth :title="__('Verifikasi email')">
    <div class="mt-4 flex flex-col gap-6">
        <flux:text class="text-center">
            {{ __('Verifikasi alamat emailmu lewat tautan yang baru kami kirim.') }}
        </flux:text>

        @if (session('status') == 'verification-link-sent')
            <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                {{ __('Tautan verifikasi baru sudah dikirim ke alamat email yang kamu daftarkan.') }}
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form novalidate method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Kirim ulang email verifikasi') }}
                </flux:button>
            </form>

            <form novalidate method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                    {{ __('Keluar') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
