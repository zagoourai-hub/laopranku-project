@php
    // Data contoh statis; diganti query App\Models\Document saat backend siap.
    $documents = [
        ['title' => 'Pengaruh Media Sosial terhadap Prestasi Belajar Mahasiswa', 'type' => 'Makalah', 'pages' => 14, 'status' => 'Menulis bab 3/5', 'color' => 'amber', 'updated' => '12 menit lalu'],
        ['title' => 'Laporan Praktikum Kimia Dasar: Titrasi Asam Basa', 'type' => 'Laporan Praktikum', 'pages' => 8, 'status' => 'Siap', 'color' => 'green', 'updated' => '2 jam lalu'],
        ['title' => 'Laporan PKL di Dinas Kominfo Kota Bandung', 'type' => 'Laporan PKL', 'pages' => 36, 'status' => 'Draft', 'color' => 'zinc', 'updated' => 'Kemarin'],
        ['title' => 'Proposal Skripsi: Sistem Rekomendasi Buku Perpustakaan', 'type' => 'Proposal', 'pages' => 22, 'status' => 'Gagal di bab 2', 'color' => 'red', 'updated' => '3 hari lalu'],
        ['title' => 'Makalah Etika Profesi Teknologi Informasi', 'type' => 'Makalah', 'pages' => 11, 'status' => 'Siap', 'color' => 'green', 'updated' => '5 hari lalu'],
        ['title' => 'Laporan Praktikum Fisika: Hukum Hooke', 'type' => 'Laporan Praktikum', 'pages' => 6, 'status' => 'Draft', 'color' => 'zinc', 'updated' => '1 minggu lalu'],
    ];
@endphp

<x-layouts::app :title="__('Dokumen')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ __('Dokumen') }}</flux:heading>
                <flux:subheading>{{ __('Semua makalah dan laporanmu') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" type="button">{{ __('Dokumen baru') }}</flux:button>
        </div>

        <flux:callout icon="information-circle" variant="secondary" inline>
            <flux:callout.heading>{{ __('Data contoh') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Daftar di bawah belum terhubung ke dokumenmu.') }}</flux:callout.text>
        </flux:callout>

        <div class="flex flex-wrap gap-3">
            <flux:input class="min-w-56 flex-1" icon="magnifying-glass" placeholder="{{ __('Cari judul...') }}" aria-label="{{ __('Cari judul') }}" />
            <flux:select class="w-44" aria-label="{{ __('Jenis dokumen') }}">
                <option>{{ __('Semua jenis') }}</option>
                <option>{{ __('Makalah') }}</option>
                <option>{{ __('Laporan Praktikum') }}</option>
                <option>{{ __('Laporan PKL') }}</option>
                <option>{{ __('Proposal') }}</option>
            </flux:select>
            <flux:select class="w-44" aria-label="{{ __('Urutan') }}">
                <option>{{ __('Terbaru diubah') }}</option>
                <option>{{ __('Judul A-Z') }}</option>
            </flux:select>
        </div>

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
                        <flux:table.row>
                            <flux:table.cell class="whitespace-normal">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $document['title'] }}</div>
                                <div class="text-sm text-zinc-500">{{ $document['type'] }} · {{ $document['pages'] }} {{ __('halaman') }}</div>
                                <flux:badge class="mt-2 sm:hidden" size="sm" :color="$document['color']">{{ $document['status'] }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="max-sm:hidden"><flux:badge size="sm" :color="$document['color']">{{ $document['status'] }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-sm:hidden">{{ __('Umum 4-3-3-3') }}</flux:table.cell>
                            <flux:table.cell class="max-sm:hidden">{{ $document['updated'] }}</flux:table.cell>
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

        <p class="text-sm text-zinc-500">{{ __('Menampilkan 1–6 dari 6 dokumen') }}</p>
    </div>
</x-layouts::app>
