@php
    use App\Enums\DocumentStatus;
    use App\Enums\DocumentType;
    use App\Models\Document;

    $documents = Document::where('user_id', auth()->id())->latest()->get();

    $typeLabels = [
        DocumentType::Makalah->value => 'Makalah',
        DocumentType::LaporanPraktikum->value => 'Laporan Praktikum',
        DocumentType::LaporanPkl->value => 'Laporan PKL',
        DocumentType::Proposal->value => 'Proposal',
    ];

    $statusBadges = [
        DocumentStatus::Draft->value => ['Draft', 'zinc'],
        DocumentStatus::Structuring->value => ['Menyusun struktur', 'amber'],
        DocumentStatus::Outlining->value => ['Menyusun outline', 'amber'],
        DocumentStatus::OutlineReady->value => ['Outline siap', 'amber'],
        DocumentStatus::Generating->value => ['Menulis isi', 'amber'],
        DocumentStatus::Ready->value => ['Siap', 'green'],
        DocumentStatus::Exported->value => ['Diekspor', 'green'],
    ];
@endphp

<x-layouts::app :title="__('Dokumen')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Dokumen') }}</flux:heading>
                <flux:subheading>{{ __('Semua makalah dan laporanmu') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" :href="route('documents.create')" wire:navigate>{{ __('Dokumen baru') }}</flux:button>
        </div>

        @if ($documents->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-white/10" data-test="documents-empty">
                <flux:icon.document-text class="size-8 text-zinc-400" />
                <flux:heading size="lg">{{ __('Belum ada dokumen') }}</flux:heading>
                <flux:text class="max-w-sm">{{ __('Mulai dari teks mentah, topik, atau file naskahmu. Rapi menyiapkan formatnya.') }}</flux:text>
                <flux:button :href="route('documents.create')" wire:navigate>{{ __('Buat dokumen pertama') }}</flux:button>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-white/10">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Judul') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Template') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Diubah') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($documents as $document)
                            @php([$statusLabel, $statusColor] = $statusBadges[$document->status->value])
                            <flux:table.row wire:key="document-{{ $document->id }}">
                                <flux:table.cell class="whitespace-normal">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $document->title }}</div>
                                    <div class="text-sm text-zinc-500">{{ $typeLabels[$document->type->value] }}</div>
                                    <flux:badge class="mt-2 sm:hidden" size="sm" :color="$statusColor">{{ $statusLabel }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="max-sm:hidden"><flux:badge size="sm" :color="$statusColor">{{ $statusLabel }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $document->template_key === 'default' ? 'Umum 4-3-3-3' : $document->template_key }}</flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $document->updated_at->locale('id')->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:dropdown align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset aria-label="{{ __('Aksi') }}" />
                                        <flux:menu>
                                            <flux:menu.item icon="eye">{{ __('Buka') }}</flux:menu.item>
                                            <flux:menu.item icon="document-duplicate">{{ __('Duplikat') }}</flux:menu.item>
                                            <flux:menu.item icon="arrow-down-tray">{{ __('Unduh DOCX') }}</flux:menu.item>
                                            <flux:menu.item icon="arrow-down-tray">{{ __('Unduh PDF') }}</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger">{{ __('Hapus') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            <p class="text-sm text-zinc-500">{{ $documents->count() }} {{ __('dokumen') }}</p>
        @endif
    </div>
</x-layouts::app>
