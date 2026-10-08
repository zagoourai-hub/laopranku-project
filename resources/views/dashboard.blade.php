<x-layouts::app :title="__('Dokumen')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Dokumen') }}</flux:heading>
                <flux:subheading>{{ __('Semua makalah dan laporanmu') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" :href="route('documents.create')" wire:navigate>{{ __('Dokumen baru') }}</flux:button>
        </div>

        <livewire:document-table />
    </div>
</x-layouts::app>
