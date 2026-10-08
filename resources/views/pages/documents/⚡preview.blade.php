<?php

use App\Models\Document;
use App\Services\Documents\Template;
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

        return $this->document->downloadUrl('pdf', inline: true);
    }

    public function roman(int $n): string
    {
        $out = '';

        foreach (['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $s => $v) {
            while ($n >= $v) {
                $out .= $s;
                $n -= $v;
            }
        }

        return $out;
    }
};
?>

@php
    $content = $document->content;
    $chapters = $content['chapters'] ?? [];
    $cover = $content['cover'] ?? [];
    $t = Template::fromConfig(config("documents.templates.{$document->template_key}") ? $document->template_key : 'default');
    $references = collect($content['references'] ?? [])->pluck('text')->sortBy(fn ($x) => mb_strtolower($x))->values();
    $coverLines = array_filter([
        isset($cover['prodi']) ? 'PROGRAM STUDI '.$cover['prodi'] : null,
        $cover['campus'] ?? null,
        $cover['year'] ?? null,
    ]);
@endphp

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6" x-data="docPreview">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Preview') }}: {{ $document->title }}</flux:heading>
            <flux:subheading>{{ __('Perkiraan tampilan kertas A4, bukan hasil akhir. Untuk hasil yang pasti, pakai Preview akurat.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('documents.show', $document)" wire:navigate icon="arrow-left">{{ __('Kembali ke editor') }}</flux:button>
            @if ($chapters !== [])
                <flux:button variant="primary" icon="document-magnifying-glass" x-on:click="open()" x-bind:disabled="loading" data-test="accurate-preview">
                    <span x-show="!loading">{{ __('Preview akurat') }}</span>
                    <span x-show="loading" x-cloak>{{ __('Menyiapkan PDF…') }}</span>
                </flux:button>
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
            <div x-show="rendering" class="mb-2 flex justify-center" data-test="preview-loading">
                <flux:badge color="zinc">{{ __('Menyusun halaman…') }}</flux:badge>
            </div>

            <div id="preview-source" hidden
                data-font="{{ $t->fontName }}" data-size="{{ $t->fontSize }}" data-line="{{ $t->lineSpacing }}"
                data-ml="{{ $t->marginLeftCm }}" data-mt="{{ $t->marginTopCm }}" data-mr="{{ $t->marginRightCm }}" data-mb="{{ $t->marginBottomCm }}"
                data-indent="{{ $t->firstLineIndentCm }}" data-hanging="{{ $t->hangingIndentCm }}">
                <section class="cover">
                    <p class="cover-type">{{ $document->type->coverLabel() }}</p>
                    <p class="cover-title">{{ mb_strtoupper($cover['title'] ?? $document->title) }}</p>
                    <p>Disusun oleh:</p>
                    <p><strong>{{ trim(($cover['name'] ?? '').' '.(isset($cover['nim']) ? "({$cover['nim']})" : '')) }}</strong></p>
                    <div class="cover-foot">
                        @foreach ($coverLines as $line)
                            <p><strong>{{ mb_strtoupper($line) }}</strong></p>
                        @endforeach
                    </div>
                </section>

                <section class="front">
                    <h1>KATA PENGANTAR</h1>
                    <p class="indent">Puji syukur kami panjatkan ke hadirat Tuhan Yang Maha Esa atas terselesaikannya {{ mb_strtolower($document->type->coverLabel()) }} berjudul "{{ $cover['title'] ?? $document->title }}" ini.</p>
                    <h1 style="margin-top: 2em">DAFTAR ISI</h1>
                    <ul class="toc">
                        @foreach ($chapters as $ci => $chapter)
                            <li><a href="#c{{ $ci }}">BAB {{ $this->roman($ci + 1) }} {{ mb_strtoupper($chapter['title']) }}</a></li>
                            @foreach ($chapter['sections'] as $si => $section)
                                <li class="toc-2"><a href="#s{{ $ci }}-{{ $si }}">{{ $ci + 1 }}.{{ $si + 1 }} {{ $section['title'] }}</a></li>
                            @endforeach
                        @endforeach
                        <li><a href="#refs">DAFTAR PUSTAKA</a></li>
                    </ul>
                </section>

                @foreach ($chapters as $ci => $chapter)
                    <section class="body {{ $loop->first ? 'first' : '' }}">
                        <h1 id="c{{ $ci }}">BAB {{ $this->roman($ci + 1) }} {{ mb_strtoupper($chapter['title']) }}</h1>
                        @foreach ($chapter['sections'] as $si => $section)
                            <h2 id="s{{ $ci }}-{{ $si }}">{{ $ci + 1 }}.{{ $si + 1 }} {{ $section['title'] }}</h2>
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
                    <h1 id="refs">DAFTAR PUSTAKA</h1>
                    @forelse ($references as $text)
                        <p class="ref">{{ $text }}</p>
                    @empty
                        <p>[Tambahkan referensi]</p>
                    @endforelse
                </section>
            </div>

            <div id="preview-pages" class="overflow-x-auto rounded-xl bg-zinc-200 p-4 dark:bg-zinc-800" data-test="preview-pages"></div>
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
