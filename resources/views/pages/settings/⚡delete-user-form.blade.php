<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Hapus akun') }}</flux:heading>
        <flux:subheading>{{ __('Hapus akunmu beserta seluruh datanya') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="outline" class="!border-red-300 !text-red-600 hover:!bg-red-50 dark:!border-red-500/40 dark:!text-red-400 dark:hover:!bg-red-500/10" data-test="delete-user-button">
            {{ __('Hapus akun') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
