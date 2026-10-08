<?php

use App\Models\Document;
use App\Services\Documents\ContentUpdater;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dokumen')] class extends Component {
    public Document $document;

    public function mount(Document $document): void
    {
        Gate::authorize('view', $document);

        $this->document = $document;
    }

    public function download(string $format = 'docx'): void
    {
        Gate::authorize('view', $this->document);

        $this->redirect($this->document->downloadUrl($format));
    }

    /**
     * Auto-save from the block editor. Returns validation errors; empty means saved.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public function save(array $content): array
    {
        Gate::authorize('update', $this->document);

        $this->skipRender();

        return app(ContentUpdater::class)->apply($this->document, $content);
    }
};
?>

@assets
@vite('resources/js/editor.js')
@endassets

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    @php($chapters = $document->content['chapters'] ?? [])

    {{-- With content, the editor state wraps the header too so the save badge and undo/redo sit beside the title. --}}
    <div class="flex flex-col gap-6"
        @if ($chapters !== [])
            wire:ignore x-data="docEditor(@js($document->content))" x-on:keydown="keys($event)" x-on:beforeunload.window="if (status === 'saving') $event.preventDefault()"
        @endif
    >
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <flux:heading size="xl" level="1" class="break-words" x-data="{ t: @js($document->title) }" x-on:doc-title.window="t = $event.detail" x-text="t">{{ $document->title }}</flux:heading>
                @if ($chapters !== [])
                    <span data-test="save-status" aria-live="polite">
                        <flux:badge size="sm" color="green" icon="check-circle" x-show="status === 'saved'">{{ __('Tersimpan') }}</flux:badge>
                        <flux:badge size="sm" color="amber" icon="arrow-path" x-show="status === 'saving'" x-cloak>{{ __('Menyimpan…') }}</flux:badge>
                        <flux:badge size="sm" color="red" icon="exclamation-triangle" x-show="status === 'error'" x-cloak>{{ __('Gagal menyimpan') }}</flux:badge>
                    </span>
                @endif
            </div>
            <flux:subheading>{{ $document->type->label() }} · {{ $document->status->label() }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('dashboard')" wire:navigate icon="arrow-left">{{ __('Kembali') }}</flux:button>
            @if ($chapters !== [])
                <div class="flex gap-1">
                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" x-on:click="undo()" x-bind:disabled="!canUndo" :aria-label="__('Urungkan')" :tooltip="__('Urungkan (Ctrl+Z)')" data-test="undo" />
                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-right" x-on:click="redo()" x-bind:disabled="!canRedo" :aria-label="__('Ulangi')" :tooltip="__('Ulangi (Ctrl+Shift+Z)')" data-test="redo" />
                </div>
                <flux:button icon="eye" :href="route('documents.preview', $document)" wire:navigate data-test="open-preview">{{ __('Preview') }}</flux:button>
                <flux:dropdown align="end">
                    <flux:button variant="primary" icon="arrow-down-tray" icon:trailing="chevron-down">{{ __('Unduh') }}</flux:button>
                    <flux:menu>
                        <flux:menu.item icon="document-text" wire:click="download" data-test="download-docx">{{ __('Unduh DOCX') }}</flux:menu.item>
                        <flux:menu.item icon="document" wire:click="download('pdf')" data-test="download-pdf">{{ __('Unduh PDF') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>
    </div>

    @if ($chapters !== [])
        <div class="rapi-editor mx-auto flex w-full max-w-3xl flex-col gap-6">
            <div x-show="status === 'error'" x-cloak class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" data-test="save-errors">
                <template x-for="error in errors.slice(0, 3)"><div x-text="error"></div></template>
            </div>

            <flux:input x-model="doc.cover.title" :label="__('Judul dokumen')" data-test="cover-title" />

            <div class="flex flex-col gap-6" x-init="sortable($el, () => doc.chapters, '.js-handle-chapter')" data-test="chapters">
                <template x-for="(chapter, ci) in doc.chapters" :key="rev + '-' + chapter.id">
                    <section class="js-sort-item space-y-4 rounded-xl border border-zinc-200 p-4 sm:p-6 dark:border-white/10" data-test="chapter">
                        <div class="flex items-center gap-2">
                            <span class="js-handle-chapter cursor-grab touch-none text-zinc-400" data-test="chapter-handle"><flux:icon.bars-3 class="size-5" /></span>
                            <span class="shrink-0 text-sm font-bold tracking-wide text-red-600 dark:text-red-400" x-text="`BAB ${roman(ci + 1)}`" data-test="chapter-number"></span>
                            <flux:input class="min-w-0 flex-1" class:input="font-semibold sm:text-base" x-model="chapter.title" :aria-label="__('Judul bab')" data-test="chapter-title" />
                            <flux:button size="xs" variant="ghost" icon="chevron-up" x-on:click="reorder(() => doc.chapters, ci, ci - 1)" x-bind:disabled="ci === 0" :aria-label="__('Naikkan bab')" data-test="chapter-up" />
                            <flux:button size="xs" variant="ghost" icon="chevron-down" x-on:click="reorder(() => doc.chapters, ci, ci + 1)" x-bind:disabled="ci === doc.chapters.length - 1" :aria-label="__('Turunkan bab')" data-test="chapter-down" />
                        </div>

                        <div class="space-y-4 ps-2 sm:ps-6" x-init="sortable($el, () => chapter.sections, '.js-handle-section')">
                            <template x-for="(section, si) in chapter.sections" :key="section.id">
                                <div class="js-sort-item space-y-2" data-test="section">
                                    <div class="flex items-center gap-2">
                                        <span class="js-handle-section cursor-grab touch-none text-zinc-400" data-test="section-handle"><flux:icon.bars-3 class="size-4" /></span>
                                        <span class="shrink-0 text-sm font-semibold tabular-nums text-zinc-500" x-text="`${ci + 1}.${si + 1}`"></span>
                                        <flux:input class="min-w-0 flex-1" size="sm" x-model="section.title" :aria-label="__('Judul subbab')" data-test="section-title" />
                                        <flux:button size="xs" variant="ghost" icon="chevron-up" x-on:click="reorder(() => chapter.sections, si, si - 1)" x-bind:disabled="si === 0" :aria-label="__('Naikkan subbab')" data-test="section-up" />
                                        <flux:button size="xs" variant="ghost" icon="chevron-down" x-on:click="reorder(() => chapter.sections, si, si + 1)" x-bind:disabled="si === chapter.sections.length - 1" :aria-label="__('Turunkan subbab')" data-test="section-down" />
                                    </div>

                                    <template x-for="block in section.blocks" :key="block.id">
                                        <div class="rounded-lg border border-transparent px-2 py-1 text-zinc-700 focus-within:border-red-500 dark:text-zinc-300" x-init="mountBlock($el, block)"></div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </section>
                </template>
            </div>
        </div>
    @else
        <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-white/10" data-test="document-empty">
            <flux:icon.document-text class="size-8 text-zinc-400" />
            <flux:heading size="lg">{{ __('Dokumen belum punya isi') }}</flux:heading>
            <flux:text class="max-w-sm">{{ __('Bab dan subbab akan tampil di sini setelah isi dokumen dibuat.') }}</flux:text>
        </div>
    @endif
    </div>
</div>
