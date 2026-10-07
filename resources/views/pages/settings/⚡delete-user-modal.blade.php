<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Yakin ingin menghapus akunmu?') }}</flux:heading>

            <flux:subheading>
                {{ __('Setelah akun dihapus, seluruh data akan terhapus permanen. Masukkan kata sandimu untuk mengonfirmasi penghapusan akun.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Kata sandi')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Batal') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="outline" class="!border-red-300 !text-red-600 hover:!bg-red-50 dark:!border-red-500/40 dark:!text-red-400 dark:hover:!bg-red-500/10" type="submit" data-test="confirm-delete-user-button">
                {{ __('Hapus akun') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
