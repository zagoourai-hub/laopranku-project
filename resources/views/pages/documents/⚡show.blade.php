<?php

use App\Models\Document;
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

    public function download(): void
    {
        Gate::authorize('view', $this->document);

        $this->redirect($this->document->downloadUrl());
    }
};
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    @php($chapters = $document->content['chapters'] ?? [])

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $document->title }}</flux:heading>
            <flux:subheading>{{ $document->type->label() }} · {{ $document->status->label() }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('dashboard')" wire:navigate icon="arrow-left">{{ __('Kembali') }}</flux:button>
            @if ($chapters !== [])
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="download" data-test="download-docx">{{ __('Unduh DOCX') }}</flux:button>
            @endif
        </div>
    </div>

    @forelse ($chapters as $chapter)
        <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-white/10" wire:key="chapter-{{ $chapter['id'] }}">
            <flux:heading size="lg">{{ $loop->iteration }}. {{ $chapter['title'] }}</flux:heading>

            @foreach ($chapter['sections'] as $section)
                <div class="space-y-2" wire:key="section-{{ $section['id'] }}">
                    <flux:heading>{{ $loop->parent->iteration }}.{{ $loop->iteration }} {{ $section['title'] }}</flux:heading>

                    @foreach ($section['blocks'] as $block)
                        @if ($block['type'] === 'list')
                            <{{ $block['ordered'] ? 'ol' : 'ul' }} class="ms-6 text-zinc-700 dark:text-zinc-300 {{ $block['ordered'] ? 'list-decimal' : 'list-disc' }}">
                                @foreach ($block['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </{{ $block['ordered'] ? 'ol' : 'ul' }}>
                        @else
                            <flux:text>{{ $block['text'] }}</flux:text>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </section>
    @empty
        <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-white/10" data-test="document-empty">
            <flux:icon.document-text class="size-8 text-zinc-400" />
            <flux:heading size="lg">{{ __('Dokumen belum punya isi') }}</flux:heading>
            <flux:text class="max-w-sm">{{ __('Bab dan subbab akan tampil di sini setelah isi dokumen dibuat.') }}</flux:text>
        </div>
    @endforelse
</div>
