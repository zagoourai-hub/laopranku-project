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

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    @php($chapters = $document->content['chapters'] ?? [])

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1" x-data="{ t: @js($document->title) }" x-on:doc-title.window="t = $event.detail" x-text="t">{{ $document->title }}</flux:heading>
            <flux:subheading>{{ $document->type->label() }} · {{ $document->status->label() }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('dashboard')" wire:navigate icon="arrow-left">{{ __('Kembali') }}</flux:button>
            @if ($chapters !== [])
                <flux:button icon="eye" :href="route('documents.preview', $document)" wire:navigate data-test="open-preview">{{ __('Preview') }}</flux:button>
                <flux:button icon="arrow-down-tray" wire:click="download('pdf')" data-test="download-pdf">{{ __('Unduh PDF') }}</flux:button>
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="download" data-test="download-docx">{{ __('Unduh DOCX') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($chapters !== [])
        <div wire:ignore x-data="docEditor(@js($document->content))" x-on:keydown="keys($event)" x-on:beforeunload.window="if (status === 'saving') $event.preventDefault()" class="rapi-editor flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-col gap-2" data-test="save-status" aria-live="polite">
                    <flux:text size="sm" x-show="status === 'saved'">{{ __('Tersimpan') }}</flux:text>
                    <flux:text size="sm" x-show="status === 'saving'" x-cloak>{{ __('Menyimpan…') }}</flux:text>
                    <div x-show="status === 'error'" x-cloak class="text-sm text-red-600 dark:text-red-400">
                        <div class="font-medium">{{ __('Gagal menyimpan') }}</div>
                        <template x-for="error in errors.slice(0, 3)"><div x-text="error"></div></template>
                    </div>
                </div>
                <div class="flex gap-1">
                    <flux:button size="sm" icon="arrow-uturn-left" x-on:click="undo()" x-bind:disabled="!canUndo" data-test="undo">{{ __('Urungkan') }}</flux:button>
                    <flux:button size="sm" icon="arrow-uturn-right" x-on:click="redo()" x-bind:disabled="!canRedo" data-test="redo">{{ __('Ulangi') }}</flux:button>
                </div>
            </div>

            <flux:input x-model="doc.cover.title" :label="__('Judul dokumen')" data-test="cover-title" />

            <div class="flex flex-col gap-6" x-init="sortable($el, () => doc.chapters, '.js-handle-chapter')" data-test="chapters">
                <template x-for="(chapter, ci) in doc.chapters" :key="rev + '-' + chapter.id">
                    <section class="js-sort-item space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-white/10" data-test="chapter">
                        <div class="flex items-center gap-2">
                            <span class="js-handle-chapter cursor-grab touch-none text-zinc-400" data-test="chapter-handle"><flux:icon.bars-3 class="size-5" /></span>
                            <span class="font-semibold text-zinc-500" x-text="`${ci + 1}.`"></span>
                            <flux:input class="flex-1" x-model="chapter.title" :aria-label="__('Judul bab')" data-test="chapter-title" />
                            <flux:button size="xs" variant="ghost" icon="chevron-up" x-on:click="reorder(() => doc.chapters, ci, ci - 1)" x-bind:disabled="ci === 0" :aria-label="__('Naikkan bab')" data-test="chapter-up" />
                            <flux:button size="xs" variant="ghost" icon="chevron-down" x-on:click="reorder(() => doc.chapters, ci, ci + 1)" x-bind:disabled="ci === doc.chapters.length - 1" :aria-label="__('Turunkan bab')" data-test="chapter-down" />
                        </div>

                        <div class="space-y-4" x-init="sortable($el, () => chapter.sections, '.js-handle-section')">
                            <template x-for="(section, si) in chapter.sections" :key="section.id">
                                <div class="js-sort-item space-y-2" data-test="section">
                                    <div class="flex items-center gap-2">
                                        <span class="js-handle-section cursor-grab touch-none text-zinc-400" data-test="section-handle"><flux:icon.bars-3 class="size-4" /></span>
                                        <span class="text-sm font-semibold text-zinc-500" x-text="`${ci + 1}.${si + 1}`"></span>
                                        <flux:input class="flex-1" x-model="section.title" :aria-label="__('Judul subbab')" data-test="section-title" />
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
