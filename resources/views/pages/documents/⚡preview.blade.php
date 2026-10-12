<?php

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Preview dokumen')] class extends Component {
    public Document $document;

    public function mount(Document $document): void
    {
        Gate::authorize('view', $document);

        $this->document = $document;
    }

    /** Signed inline PDF link, created on click so it stays fresh. */
    public function accurateUrl(): string
    {
        Gate::authorize('view', $this->document);
        abort_unless(app(\App\Services\Billing\PlanLimits::class)->canExportPdf(auth()->user()), 403);

        return $this->document->downloadUrl('pdf', inline: true);
    }
};
?>

@php
    $content = $document->content;
    $chapters = $content['chapters'] ?? [];
    $t = \App\Services\Documents\Template::fromConfig(config("documents.templates.{$document->template_key}") ? $document->template_key : 'default');
    // The same plan DocxBuilder renders: what appears, in what order, and how it is numbered.
    $plan = $chapters === [] ? null : app(\App\Services\Documents\DocumentLayout::class)->plan($content, $document->education_level);
    $plans = app(\App\Services\Billing\PlanLimits::class);
@endphp

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6" x-data="docPreview">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="break-words">{{ __('Preview') }}: {{ $document->title }}</flux:heading>
            <flux:subheading>{{ __('Perkiraan tampilan kertas A4, bukan hasil akhir. Untuk hasil yang pasti, pakai Preview akurat.') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('documents.show', $document)" wire:navigate icon="arrow-left">{{ __('Kembali ke editor') }}</flux:button>
            @if ($chapters !== [])
                @if ($plans->canExportPdf(auth()->user()))
                    <flux:button variant="primary" icon="document-magnifying-glass" x-on:click="open()" x-bind:disabled="loading" data-test="accurate-preview">
                        <span x-show="!loading">{{ __('Preview akurat') }}</span>
                        <span x-show="loading" x-cloak>{{ __('Menyiapkan PDF…') }}</span>
                    </flux:button>
                @else
                    <flux:tooltip :content="__('Preview akurat (PDF) khusus paket Pro')">
                        <flux:button variant="primary" icon="lock-closed" :href="$plans->upgradeUrl()" data-test="accurate-preview-locked">{{ __('Preview akurat (Pro)') }}</flux:button>
                    </flux:tooltip>
                @endif
            @endif
        </div>
    </div>

    @if ($chapters === [])
        <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-white/10" data-test="preview-empty">
            <flux:icon.document-text class="size-8 text-zinc-400" />
            <flux:heading size="lg">{{ __('Dokumen belum punya isi') }}</flux:heading>
            <flux:text class="max-w-sm">{{ __('Preview tampil setelah dokumen punya bab dan subbab.') }}</flux:text>
        </div>
    @else
        <div x-show="error" x-cloak class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" data-test="accurate-error" x-text="error"></div>

        <div x-show="pdf" x-cloak class="space-y-2" data-test="accurate-frame">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Hasil akurat (PDF dari DOCX)') }}</flux:heading>
                <flux:button size="sm" variant="ghost" x-on:click="close()">{{ __('Tutup') }}</flux:button>
            </div>
            <iframe x-bind:src="pdf" class="h-[80vh] w-full rounded-lg border border-zinc-200 bg-white dark:border-white/10" title="{{ __('Preview akurat') }}"></iframe>
        </div>

        {{-- paged.js rewrites this DOM, so Livewire must leave it alone. Paper stays white in dark mode. --}}
        <div wire:ignore class="relative">
            <div x-show="rendering" class="flex justify-center rounded-xl bg-zinc-100 py-16 dark:bg-zinc-800" data-test="preview-loading">
                <flux:badge color="zinc" icon="arrow-path">{{ __('Menyusun halaman…') }}</flux:badge>
            </div>

            <div id="preview-source" hidden
                data-font="{{ $t->fontName }}" data-size="{{ $t->fontSize }}" data-line="{{ $t->lineSpacing }}"
                data-ml="{{ $t->marginLeftCm }}" data-mt="{{ $t->marginTopCm }}" data-mr="{{ $t->marginRightCm }}" data-mb="{{ $t->marginBottomCm }}"
                data-indent="{{ $t->firstLineIndentCm }}" data-hanging="{{ $t->hangingIndentCm }}">
                <section class="cover">
                    <p class="cover-type">{{ $plan['cover']['label'] }}</p>
                    <p class="cover-title">{{ $plan['cover']['title'] }}</p>
                    <p><strong>Disusun oleh:</strong></p>
                    @if ($plan['cover']['name'] !== null)
                        <p><strong><u>{{ $plan['cover']['name'] }}</u></strong></p>
                    @endif
                    @if ($plan['cover']['nim'] !== null)
                        <p><strong>{{ $plan['cover']['nim'] }}</strong></p>
                    @endif
                    <div class="cover-foot">
                        @foreach ($plan['cover']['foot'] as $line)
                            <p><strong>{{ $line }}</strong></p>
                        @endforeach
                    </div>
                </section>

                {{-- One front section: its roman numbering and page counter span every item in it. --}}
                <section class="front">
                    @foreach ($plan['front'] as $item)
                        @if ($item['key'] === 'approval')
                            <h1 id="{{ $item['id'] }}" class="{{ $loop->first ? '' : 'new-page' }}">{{ $item['title'] }}</h1>
                            <p class="indent">{{ $plan['approval']['sentence'] }}</p>
                            @foreach ($plan['approval']['lines'] as $line)
                                <p>{{ $line['label'] }}: {{ $line['value'] }}</p>
                            @endforeach
                        @elseif ($item['key'] === 'abstract')
                            <h1 id="{{ $item['id'] }}" class="{{ $loop->first ? '' : 'new-page' }}">{{ $item['title'] }}</h1>
                            <p class="indent">{{ $plan['abstract'] }}</p>
                        @elseif ($item['key'] === 'preface')
                            <h1 id="{{ $item['id'] }}" class="{{ $loop->first ? '' : 'new-page' }}">{{ $plan['preface']['title'] }}</h1>
                            <p class="indent">{{ $plan['preface']['text'] }}</p>
                            <p class="signature">{{ $plan['preface']['signature'] }}</p>
                        @elseif ($item['key'] === 'toc')
                            <h1 id="{{ $item['id'] }}" class="new-page">{{ $item['title'] }}</h1>
                            <ul class="toc">
                                @foreach ($plan['toc'] as $entry)
                                    <li class="toc-{{ $entry['level'] }}{{ ($entry['front'] ?? false) ? ' toc-front' : '' }}"><a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                </section>

                @foreach ($plan['chapters'] as $chapter)
                    <section class="body {{ $loop->first ? 'first' : '' }}">
                        <h1 id="{{ $chapter['id'] }}">{{ $chapter['label'] }}<br>{{ $chapter['title'] }}</h1>
                        @foreach ($chapter['sections'] as $section)
                            <h2 id="{{ $section['id'] }}">{{ $section['number'] }} {{ $section['title'] }}</h2>
                            @foreach ($section['blocks'] as $block)
                                @if ($block['type'] === 'list')
                                    <{{ $block['ordered'] ? 'ol' : 'ul' }}>
                                        @foreach ($block['items'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </{{ $block['ordered'] ? 'ol' : 'ul' }}>
                                @else
                                    <p class="indent">{{ $block['text'] }}</p>
                                @endif
                            @endforeach
                        @endforeach
                    </section>
                @endforeach

                <section class="body">
                    <h1 id="{{ $plan['references']['id'] }}">{{ $plan['references']['title'] }}</h1>
                    @forelse ($plan['references']['entries'] as $line)
                        <p class="ref">{{ $line }}</p>
                    @empty
                        <p>[Tambahkan referensi]</p>
                    @endforelse
                </section>

                @if ($plan['appendices'] !== null)
                    <section class="body">
                        <h1 id="{{ $plan['appendices']['id'] }}">{{ $plan['appendices']['title'] }}</h1>
                        @forelse ($plan['appendices']['items'] as $appendix)
                            <h2 id="{{ $appendix['id'] }}">{{ $appendix['title'] }}</h2>
                            @foreach ($appendix['lines'] as $line)
                                <p class="indent">{{ $line }}</p>
                            @endforeach
                        @empty
                            <p>[Tambahkan lampiran]</p>
                        @endforelse
                    </section>
                @endif
            </div>

            {{-- Laid out off-screen while paged.js works, so half-built sheets never flash. --}}
            <div id="preview-pages" class="overflow-x-auto rounded-xl bg-zinc-200 p-2 sm:p-4 dark:bg-zinc-800" x-bind:class="rendering && 'invisible absolute inset-x-0 top-0'" data-test="preview-pages"></div>
        </div>
    @endif
</div>

@assets
@vite('resources/js/preview.js')
<style>
    .pagedjs_pages { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
    .pagedjs_page { background: #fff; box-shadow: 0 1px 6px rgb(0 0 0 / .25); flex: none; }
</style>
@endassets
