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
                <flux:button icon="arrow-down-tray" wire:click="download('pdf')" data-test="download-pdf">{{ __('Unduh PDF') }}</flux:button>
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="download" data-test="download-docx">{{ __('Unduh DOCX') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($chapters !== [])
        <div wire:ignore x-data="docEditor(@js($document->content))" x-on:beforeunload.window="if (status === 'saving') $event.preventDefault()" class="rapi-editor flex flex-col gap-6">
            <div class="flex flex-col gap-2" data-test="save-status" aria-live="polite">
                <flux:text size="sm" x-show="status === 'saved'">{{ __('Tersimpan') }}</flux:text>
                <flux:text size="sm" x-show="status === 'saving'" x-cloak>{{ __('Menyimpan…') }}</flux:text>
                <div x-show="status === 'error'" x-cloak class="text-sm text-red-600 dark:text-red-400">
                    <div class="font-medium">{{ __('Gagal menyimpan') }}</div>
                    <template x-for="error in errors.slice(0, 3)"><div x-text="error"></div></template>
                </div>
            </div>

            <flux:input x-model="doc.cover.title" :label="__('Judul dokumen')" data-test="cover-title" />

            <template x-for="(chapter, ci) in doc.chapters" :key="chapter.id">
                <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-zinc-500" x-text="`${ci + 1}.`"></span>
                        <flux:input class="flex-1" x-model="chapter.title" :aria-label="__('Judul bab')" data-test="chapter-title" />
                    </div>

                    <template x-for="(section, si) in chapter.sections" :key="section.id">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-zinc-500" x-text="`${ci + 1}.${si + 1}`"></span>
                                <flux:input class="flex-1" x-model="section.title" :aria-label="__('Judul subbab')" data-test="section-title" />
                            </div>

                            <template x-for="block in section.blocks" :key="block.id">
                                <div class="rounded-lg border border-transparent px-2 py-1 text-zinc-700 focus-within:border-red-500 dark:text-zinc-300" x-init="mountBlock($el, block)"></div>
                            </template>
                        </div>
                    </template>
                </section>
            </template>
        </div>
    @else
        <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-white/10" data-test="document-empty">
            <flux:icon.document-text class="size-8 text-zinc-400" />
            <flux:heading size="lg">{{ __('Dokumen belum punya isi') }}</flux:heading>
            <flux:text class="max-w-sm">{{ __('Bab dan subbab akan tampil di sini setelah isi dokumen dibuat.') }}</flux:text>
        </div>
    @endif
</div>
