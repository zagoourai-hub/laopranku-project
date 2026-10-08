<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10">
    <flux:separator variant="subtle" class="mb-8" />

    <div class="space-y-6 rounded-xl border border-red-200 p-5 dark:border-red-500/30" data-test="danger-zone">
    <div class="relative">
        <flux:heading>{{ __('Hapus akun') }}</flux:heading>
        <flux:subheading>{{ __('Hapus akunmu beserta seluruh datanya') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="outline" class="!border-red-300 !text-red-600 hover:!bg-red-50 dark:!border-red-500/40 dark:!text-red-400 dark:hover:!bg-red-500/10" data-test="delete-user-button">
            {{ __('Hapus akun') }}
        </flux:button>
    </flux:modal.trigger>

    </div>

    <livewire:pages::settings.delete-user-modal />
</section>
