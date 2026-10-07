<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Pengaturan tampilan')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Pengaturan tampilan') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Tampilan')" :subheading="__('Atur tampilan untuk akunmu')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Terang') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Gelap') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('Sistem') }}</flux:radio>
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
