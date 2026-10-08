<?php

use App\Models\Document;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $deletingId = null;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    #[Computed]
    public function documents()
    {
        return Document::where('user_id', Auth::id())->latest()->get();
    }

    public function duplicate(int $id): void
    {
        $document = Document::findOrFail($id);

        Gate::authorize('view', $document);
        Gate::authorize('create', Document::class);

        $document->duplicate();

        unset($this->documents);
        Flux::toast(variant: 'success', text: __('Dokumen diduplikat'));
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('delete', Document::findOrFail($id));

        $this->deletingId = $id;
        Flux::modal('delete-document')->show();
    }

    public function delete(): void
    {
        $document = Document::findOrFail($this->deletingId);

        Gate::authorize('delete', $document);

        $document->delete();

        $this->deletingId = null;
        unset($this->documents);
        Flux::modal('delete-document')->close();
        Flux::toast(variant: 'success', text: __('Dokumen dihapus'));
    }

    public function download(int $id): void
    {
        $document = Document::findOrFail($id);

        Gate::authorize('view', $document);

        if (empty($document->content['chapters'] ?? [])) {
            Flux::toast(variant: 'warning', text: __('Dokumen belum punya isi untuk diunduh'));

            return;
        }

        $this->redirect($document->downloadUrl());
    }
};
?>

<div>
    @php
        $documents = $this->documents;
    @endphp

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
                        @php
                            $hasContent = ! empty($document->content['chapters'] ?? []);
                        @endphp
                        <flux:table.row wire:key="document-{{ $document->id }}">
                            <flux:table.cell class="whitespace-normal">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $document->title }}</div>
                                <div class="text-sm text-zinc-500">{{ $document->type->label() }}</div>
                                <flux:badge class="mt-2 sm:hidden" size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="max-sm:hidden"><flux:badge size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-sm:hidden">{{ $document->template_key === 'default' ? 'Umum 4-3-3-3' : $document->template_key }}</flux:table.cell>
                            <flux:table.cell class="max-sm:hidden">{{ $document->updated_at->locale('id')->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset aria-label="{{ __('Aksi') }}" />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" :href="route('documents.show', $document)" wire:navigate>{{ __('Buka') }}</flux:menu.item>
                                        <flux:menu.item icon="document-duplicate" wire:click="duplicate({{ $document->id }})" data-test="duplicate-document">{{ __('Duplikat') }}</flux:menu.item>
                                        @if ($hasContent)
                                            <flux:menu.item icon="arrow-down-tray" wire:click="download({{ $document->id }})" data-test="download-docx">{{ __('Unduh DOCX') }}</flux:menu.item>
                                        @else
                                            <flux:tooltip :content="__('Dokumen belum punya isi untuk diunduh')">
                                                <span class="block" data-test="download-disabled"><flux:menu.item icon="arrow-down-tray" disabled>{{ __('Unduh DOCX') }}</flux:menu.item></span>
                                            </flux:tooltip>
                                        @endif
                                        <flux:menu.item icon="arrow-down-tray">{{ __('Unduh PDF') }}</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $document->id }})" data-test="delete-document">{{ __('Hapus') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <p class="mt-6 text-sm text-zinc-500">{{ $documents->count() }} {{ __('dokumen') }}</p>
    @endif

    <flux:modal name="delete-document" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Hapus dokumen?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Dokumen akan dihapus dari daftarmu.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Batal') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="delete" data-test="confirm-delete">{{ __('Hapus') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
