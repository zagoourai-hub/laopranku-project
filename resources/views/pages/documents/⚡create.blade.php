<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use App\Jobs\GenerateOutline;
use App\Jobs\StructureDocument;
use App\Models\Document;
use App\Services\Ai\GenerationLimiter;
use App\Services\Billing\PlanLimits;
use App\Services\Documents\ImportParser;
use App\Services\Documents\SchemaValidator;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Dokumen baru')] class extends Component {
    use WithFileUploads;

    public string $educationLevel = 'mahasiswa';
    public string $type = 'makalah';
    public string $title = '';
    public string $name = '';
    public string $nim = '';
    public string $prodi = '';
    public string $campus = '';
    public string $year = '';

    public string $inputMode = 'paste';
    public string $rawText = '';
    public string $topic = '';
    public int $chapterCount = 3;
    public string $length = 'sedang';
    public string $style = 'formal';
    public string $instructions = '';
    public string $sourcesText = '';

    /** @var array<string, string> */
    public array $facts = [];
    public $upload = null;

    public function mount(): void
    {
        $this->name = Auth::user()->is_guest ? '' : Auth::user()->name;
        $this->year = (string) now()->year;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'educationLevel' => ['required', Rule::enum(EducationLevel::class)],
            'type' => ['required', Rule::enum(DocumentType::class), Rule::notIn($this->level()->value === 'mahasiswa' ? [] : ['proposal'])],
            'title' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:150'],
            'nim' => ['nullable', 'regex:/^\d+$/', 'max:20'],
            'prodi' => ['nullable', 'string', 'max:150'],
            'campus' => ['nullable', 'string', 'max:150'],
            'year' => ['nullable', 'digits:4'],
            'inputMode' => ['required', Rule::in(['paste', 'topic', 'upload'])],
            'rawText' => ['nullable', 'string', 'max:200000'],
            'topic' => [Rule::requiredIf($this->inputMode === 'topic'), 'nullable', 'string', 'min:10', 'max:500'],
            'chapterCount' => ['required', 'integer', 'between:3,7'],
            'length' => ['required', Rule::in(['ringkas', 'sedang', 'panjang'])],
            'style' => ['required', Rule::in(['formal', 'semi_formal', 'ringkas'])],
            'instructions' => ['nullable', 'string', 'max:500'],
            'sourcesText' => ['nullable', 'string', 'max:5000'],
            ...$this->factRules(),
            'upload' => [Rule::requiredIf($this->inputMode === 'upload'), 'nullable', 'file', 'extensions:md,txt,docx', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Judul dokumen wajib diisi.',
            'type.required' => 'Pilih jenis dokumen.',
            'nim.regex' => 'Nomor induk hanya boleh berisi angka.',
            'year.digits' => 'Tahun harus 4 digit.',
            'topic.required' => 'Topik wajib diisi.',
            'topic.min' => 'Topik minimal 10 karakter.',
            'topic.max' => 'Topik maksimal 500 karakter.',
            'chapterCount.between' => 'Jumlah bab harus 3 sampai 7.',
            'instructions.max' => 'Instruksi tambahan maksimal 500 karakter.',
            'sourcesText.max' => 'Sumber referensi maksimal 5.000 karakter.',
            'facts.*.required' => ':Attribute wajib diisi.',
            'facts.*.max' => ':Attribute maksimal 5.000 karakter.',
            'upload.required' => 'Pilih file yang akan diunggah.',
            'upload.extensions' => 'File harus berformat .md, .txt, atau .docx.',
            'upload.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }

    /**
     * Source data is required only on the topic path, where AI writes from it (PRD A5).
     *
     * @return array<string, array<int, string>>
     */
    protected function factRules(): array
    {
        if ($this->inputMode !== 'topic') {
            return [];
        }

        return collect($this->factFields)->mapWithKeys(fn (array $field, string $key): array => [
            "facts.{$key}" => ['required', 'string', 'max:5000'],
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return collect($this->factFields)->mapWithKeys(fn (array $field, string $key): array => [
            "facts.{$key}" => strtolower($field['label']),
        ])->all();
    }

    protected function documentType(): DocumentType
    {
        return DocumentType::tryFrom($this->type) ?? DocumentType::Makalah;
    }

    /**
     * @return array<string, array{label: string, list: bool}>
     */
    #[\Livewire\Attributes\Computed]
    public function factFields(): array
    {
        return $this->documentType()->factFields();
    }

    public function updatedType(): void
    {
        $this->chapterCount = $this->documentType()->defaultChapterCount();
    }

    /**
     * Non-empty lines without manual bullets or numbering.
     *
     * @return list<string>
     */
    protected static function lines(string $text): array
    {
        return array_values(array_filter(array_map(
            fn (string $line): string => trim((string) preg_replace('/^\s*(?:[-•*]|\d+[.)])\s+/u', '', $line)),
            preg_split('/\R/u', $text) ?: [],
        ), fn (string $line): bool => $line !== ''));
    }

    /**
     * @return array<string, string|list<string>>
     */
    protected function briefFacts(): array
    {
        return collect($this->factFields)->mapWithKeys(fn (array $field, string $key): array => [
            $key => $field['list'] ? self::lines($this->facts[$key] ?? '') : trim($this->facts[$key] ?? ''),
        ])->all();
    }

    /**
     * @return list<array{id: string, text: string, url: string|null, doi: null}>
     */
    protected function sources(): array
    {
        return array_map(fn (string $line): array => [
            'id' => (string) Str::uuid(),
            'text' => $line,
            'url' => filter_var($line, FILTER_VALIDATE_URL) !== false ? $line : null,
            'doi' => null,
        ], self::lines($this->sourcesText));
    }

    protected function level(): EducationLevel
    {
        return EducationLevel::tryFrom($this->educationLevel) ?? EducationLevel::Mahasiswa;
    }

    public function updatedEducationLevel(): void
    {
        // Proposal (usulan skripsi) is not offered to school students.
        if ($this->level() !== EducationLevel::Mahasiswa && $this->type === 'proposal') {
            $this->type = 'makalah';
        }
    }

    /**
     * @return array<string, string>
     */
    #[\Livewire\Attributes\Computed]
    public function coverLabels(): array
    {
        return $this->level()->coverLabels();
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    #[\Livewire\Attributes\Computed]
    public function typeOptions(): array
    {
        $options = [
            'makalah' => ['label' => 'Makalah', 'description' => 'Pendahuluan, pembahasan, penutup, daftar pustaka'],
            'laporan_praktikum' => ['label' => 'Laporan praktikum', 'description' => 'Metodologi, data pengamatan, grafik'],
            'laporan_pkl' => ['label' => $this->level() === EducationLevel::Smk ? 'Laporan Prakerin/PKL' : 'Laporan PKL', 'description' => 'Profil instansi, pelaksanaan magang'],
            'proposal' => ['label' => 'Proposal', 'description' => 'Usulan penelitian, RAB, dan jadwal'],
        ];

        if ($this->level() !== EducationLevel::Mahasiswa) {
            unset($options['proposal']);
        }

        return $options;
    }

    public function save(): void
    {
        if (! app(PlanLimits::class)->canCreateDocument(Auth::user())) {
            $this->addError('quota', __('Kuota dokumen bulan ini sudah habis. Upgrade ke Pro untuk membuat dokumen tanpa batas.'));

            return;
        }

        Gate::authorize('create', Document::class);

        $this->validate();

        $brief = [
            'input' => $this->inputMode,
            'topic' => $this->inputMode === 'topic' ? $this->topic : '',
            'chapter_count' => $this->chapterCount,
            'length' => $this->length,
            'instructions' => $this->inputMode === 'topic' ? $this->instructions : '',
        ];

        if ($this->inputMode === 'paste') {
            $brief['raw_text'] = $this->rawText;
        }

        if ($this->inputMode === 'topic' && $this->factFields !== []) {
            $brief['facts'] = $this->briefFacts();
        }

        $chapters = [];

        if ($this->inputMode === 'upload') {
            try {
                $parsed = app(ImportParser::class)->parse($this->upload);
            } catch (\Throwable $e) {
                report($e);
                $this->addError('upload', 'File tidak bisa dibaca. Pastikan berformat .docx, .md, atau .txt yang valid.');

                return;
            }

            $chapters = $parsed['chapters'] ?? [];

            if (isset($parsed['paragraphs'])) {
                $brief['paragraphs'] = $parsed['paragraphs'];
            }
        }

        $content = [
            'schema_version' => 1,
            'type' => $this->type,
            'cover' => [
                'title' => $this->title,
                'name' => $this->name,
                'nim' => $this->nim,
                'prodi' => $this->prodi,
                'campus' => $this->campus,
                'year' => $this->year,
            ],
            'style' => $this->style,
            'chapters' => $chapters,
            'references' => [],
            'sources' => $this->inputMode === 'topic' ? $this->sources() : [],
            'brief' => $brief,
        ];

        if (($errors = app(SchemaValidator::class)->validate($content)) !== []) {
            if ($this->inputMode === 'upload') {
                $this->addError('upload', 'Struktur heading di file tidak valid. Periksa judul bab dan subbab.');
            } else {
                report(new \RuntimeException('Draft content failed schema validation: '.implode('; ', $errors)));
                $this->addError($this->inputMode === 'topic' ? 'topic' : 'rawText', 'Data belum lengkap atau tidak valid. Periksa kembali isian.');
            }

            return;
        }

        $needsAi = $this->inputMode === 'topic' || $chapters === [];

        if ($needsAi && ! app(GenerationLimiter::class)->consume()) {
            $this->addError('generate', app(GenerationLimiter::class)->dailyQuotaExhausted()
                ? __('Kuota generate AI hari ini sudah habis. Coba lagi besok atau upgrade ke Pro.')
                : __('Terlalu banyak permintaan AI. Coba lagi beberapa menit lagi.'));

            return;
        }

        if ($this->inputMode === 'upload') {
            $content['brief']['upload_path'] = $this->upload->store('document-uploads/'.Auth::id());
        }

        try {
            $document = $this->createDocument($content);

            if ($this->inputMode === 'topic') {
                GenerateOutline::dispatch($document);
            } elseif ($needsAi) {
                StructureDocument::dispatch($document);
            }
        } catch (\Throwable $e) {
            if (isset($content['brief']['upload_path'])) {
                Storage::delete($content['brief']['upload_path']);
            }

            throw $e;
        }

        Flux::toast(variant: 'success', text: __('Dokumen draft dibuat'));

        $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function createDocument(array $content): Document
    {
        return Document::create([
            'user_id' => Auth::id(),
            'title' => $this->title,
            'type' => $this->type,
            'education_level' => $this->level(),
            'status' => DocumentStatus::Draft,
            'template_key' => 'default',
            'content' => $content,
        ]);
    }
};
?>

<div class="mx-auto w-full max-w-6xl">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dokumen') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Baru') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="xl" level="1">{{ __('Dokumen baru') }}</flux:heading>
    <flux:subheading>{{ __('Isi data cover dan pilih cara memasukkan isi dokumen.') }}</flux:subheading>

    @php($quotaFull = ! app(\App\Services\Billing\PlanLimits::class)->canCreateDocument(auth()->user()))
    @if ($quotaFull)
        <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle" data-test="quota-full">
            <flux:callout.heading>{{ __('Kuota dokumen bulan ini sudah habis') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Paket Free dibatasi :n dokumen per bulan. Upgrade ke Pro untuk dokumen tanpa batas dan unduh PDF.', ['n' => config('plans.free_documents_per_month')]) }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" :href="app(\App\Services\Billing\PlanLimits::class)->upgradeUrl()" data-test="upgrade-cta">{{ __('Upgrade ke Pro') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <form novalidate wire:submit="save" class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="flex min-w-0 flex-col gap-10">
            <section class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Jenjang') }}</flux:heading>
                <flux:radio.group wire:model.live="educationLevel" variant="segmented" aria-label="{{ __('Jenjang') }}" data-test="level-group">
                    @foreach (\App\Enums\EducationLevel::cases() as $level)
                        <flux:radio :value="$level->value" :label="$level->label()" wire:key="level-{{ $level->value }}" />
                    @endforeach
                </flux:radio.group>
            </section>

            <section class="flex flex-col gap-4 border-t border-zinc-200 pt-8 dark:border-white/10">
                <flux:heading size="lg">{{ __('Jenis dokumen') }}</flux:heading>
                <flux:radio.group wire:model.live="type" variant="cards" class="max-sm:flex-col sm:grid sm:grid-cols-2" aria-label="{{ __('Jenis dokumen') }}" data-test="type-group">
                    @foreach ($this->typeOptions as $value => $option)
                        <flux:radio :value="$value" :label="$option['label']" :description="$option['description']" wire:key="type-{{ $value }}" />
                    @endforeach
                </flux:radio.group>
                <flux:error name="type" />
            </section>

            <section class="flex flex-col gap-4 border-t border-zinc-200 pt-8 dark:border-white/10">
                <flux:heading size="lg">{{ __('Data cover') }}</flux:heading>
                <flux:input wire:model.live.debounce.300ms="title" :label="__('Judul dokumen')" :placeholder="$educationLevel === 'mahasiswa' ? __('Pengaruh Media Sosial terhadap Prestasi Belajar Mahasiswa') : __('Pengaruh Media Sosial terhadap Prestasi Belajar Siswa')" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live.debounce.300ms="name" :label="$this->coverLabels['name']" />
                    <flux:input wire:model.live.debounce.300ms="nim" :label="$this->coverLabels['nim']" inputmode="numeric" :placeholder="$educationLevel === 'mahasiswa' ? '2201234567' : '0012345678'" />
                    <flux:input wire:model.live.debounce.300ms="prodi" :label="$this->coverLabels['prodi']" :placeholder="$educationLevel === 'mahasiswa' ? 'Teknik Informatika' : ($educationLevel === 'smk' ? 'XII RPL 1' : 'XII IPA 2')" />
                    <flux:input wire:model.live.debounce.300ms="campus" :label="$this->coverLabels['campus']" :placeholder="$educationLevel === 'mahasiswa' ? 'Universitas Padjadjaran' : 'SMA Negeri 1 Bandung'" />
                    <flux:input wire:model.live.debounce.300ms="year" :label="__('Tahun')" inputmode="numeric" maxlength="4" />
                </div>
            </section>

            <section class="flex flex-col gap-4 border-t border-zinc-200 pt-8 dark:border-white/10">
                <flux:heading size="lg">{{ __('Cara mengisi') }}</flux:heading>
                <flux:radio.group wire:model.live="inputMode" variant="cards" class="max-sm:flex-col sm:grid sm:grid-cols-3" aria-label="{{ __('Cara mengisi') }}" data-test="input-mode-group">
                    <flux:radio value="paste" icon="clipboard-document" :label="__('Tempel teks')" />
                    <flux:radio value="topic" icon="pencil-square" :label="__('Tulis topik')" />
                    <flux:radio value="upload" icon="arrow-up-tray" :label="__('Upload file')" />
                </flux:radio.group>

                @if ($inputMode === 'paste')
                    <flux:textarea wire:model="rawText" :label="__('Teks mentah')" rows="10" :placeholder="__('Tempel naskahmu di sini. Isi kalimat tidak akan diubah.')" class="font-mono" />
                @elseif ($inputMode === 'topic')
                    <flux:input wire:model="topic" :label="__('Topik / judul')" :placeholder="__('Minimal 10 karakter')" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <flux:select wire:model="chapterCount" :label="__('Jumlah bab')">
                            @foreach (range(3, 7) as $count)
                                <option value="{{ $count }}">{{ $count }} {{ __('bab') }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="length" :label="__('Panjang per bab')">
                            <option value="ringkas">{{ __('Ringkas') }}</option>
                            <option value="sedang">{{ __('Sedang') }}</option>
                            <option value="panjang">{{ __('Panjang') }}</option>
                        </flux:select>
                        <flux:select wire:model="style" :label="__('Gaya bahasa')">
                            <option value="formal">{{ __('Formal akademik') }}</option>
                            <option value="semi_formal">{{ __('Semi-formal') }}</option>
                            <option value="ringkas">{{ __('Ringkas') }}</option>
                        </flux:select>
                    </div>
                    <flux:textarea wire:model="instructions" :label="__('Instruksi tambahan (opsional)')" rows="3" maxlength="500" />

                    @if ($this->factFields !== [])
                        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 sm:p-5 dark:border-white/10" data-test="facts-section">
                            <div>
                                <flux:heading>{{ $type === 'laporan_pkl' ? ($educationLevel === 'smk' ? __('Data pokok Prakerin/PKL') : __('Data pokok PKL')) : __('Data pokok praktikum') }}</flux:heading>
                                <flux:text class="mt-1">{{ __('Wajib diisi. AI hanya menarasikan data ini dan tidak menambah angka, nama, atau kegiatan baru.') }}</flux:text>
                            </div>
                            @foreach ($this->factFields as $key => $field)
                                @if ($field['list'] || $key === 'observations')
                                    <flux:textarea wire:model="facts.{{ $key }}" :label="__($field['label'])" :description="$field['list'] ? __('Satu item per baris.') : null" rows="4" wire:key="fact-{{ $type }}-{{ $key }}" />
                                @else
                                    <flux:input wire:model="facts.{{ $key }}" :label="__($field['label'])" :placeholder="$key === 'period' ? __('Contoh: 1 Juli – 31 Agustus 2026') : null" wire:key="fact-{{ $type }}-{{ $key }}" />
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <flux:textarea wire:model="sourcesText" :label="__('Sumber referensi (opsional)')" :description="__('Satu sumber per baris: buku, jurnal, atau URL. AI hanya mengutip sumber yang kamu tulis.')" rows="3" />
                @else
                    <flux:field>
                        <flux:label>{{ __('File naskah') }}</flux:label>
                        <input type="file" wire:model="upload" accept=".md,.txt,.docx" class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-lg file:border file:border-zinc-300 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-900 dark:text-zinc-400 dark:file:border-white/10 dark:file:bg-zinc-800 dark:file:text-white" />
                        <flux:description>{{ __('Format .md, .txt, atau .docx, maksimal 10 MB.') }}</flux:description>
                        <flux:error name="upload" />
                    </flux:field>
                @endif
            </section>

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-3 border-t border-zinc-200 bg-white/90 py-4 backdrop-blur dark:border-white/10 dark:bg-zinc-900/90">
                <flux:button :href="route('dashboard')" variant="ghost" wire:navigate>{{ __('Batal') }}</flux:button>
                <flux:error name="generate" />
                                <flux:button type="submit" variant="primary" :disabled="$quotaFull" data-test="create-document-button">{{ __('Buat draf') }}</flux:button>
            </div>
        </div>

        <aside class="flex flex-col gap-3 lg:sticky lg:top-6 lg:self-start" aria-label="{{ __('Pratinjau cover') }}">
            <div class="flex items-center justify-between text-xs">
                <span class="font-medium uppercase tracking-wide text-zinc-500">{{ __('Pratinjau cover') }}</span>
                <span class="text-zinc-500">{{ __('Terisi dari form') }}</span>
            </div>

            <div class="paper mx-auto flex aspect-[1/1.414] w-full max-w-xs flex-col items-center justify-between border border-zinc-300 p-8 text-center shadow-sm" data-test="cover-preview">
                <div class="w-full">
                    <p class="text-[10px] font-bold tracking-widest">{{ strtoupper($this->typeOptions[$type]['label'] ?? '') }}</p>
                    <p class="mt-3 text-sm font-bold leading-tight">{{ $title !== '' ? strtoupper($title) : strtoupper(__('Judul dokumen')) }}</p>
                </div>
                <div class="grid h-12 w-16 place-items-center border border-dashed border-zinc-400 text-[8px] text-zinc-500">{{ $educationLevel === 'mahasiswa' ? __('LOGO KAMPUS') : __('LOGO SEKOLAH') }}</div>
                <div class="w-full text-[10px] leading-snug">
                    <p>{{ __('Disusun oleh:') }}</p>
                    <p class="font-bold">{{ $name !== '' ? $name : '—' }}</p>
                    @if ($nim !== '')
                        <p>{{ $this->coverLabels['nim'] }} {{ $nim }}</p>
                    @endif
                    <p class="mt-3 font-bold">{{ strtoupper(trim($prodi)) }}</p>
                    <p class="font-bold">{{ strtoupper(trim($campus)) }}</p>
                    <p class="font-bold">{{ $year }}</p>
                </div>
            </div>
            <p class="text-center text-xs text-zinc-500">{{ __('Margin 4-3-3-3 · Times New Roman 12 · spasi 1.5') }}</p>
        </aside>
    </form>
</div>
