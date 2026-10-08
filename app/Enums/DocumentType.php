<?php

namespace App\Enums;

enum DocumentType: string
{
    case Makalah = 'makalah';
    case LaporanPraktikum = 'laporan_praktikum';
    case LaporanPkl = 'laporan_pkl';
    case Proposal = 'proposal';

    public function label(): string
    {
        return match ($this) {
            self::Makalah => 'Makalah',
            self::LaporanPraktikum => 'Laporan Praktikum',
            self::LaporanPkl => 'Laporan PKL',
            self::Proposal => 'Proposal',
        };
    }

    public function defaultChapterCount(): int
    {
        return match ($this) {
            self::Makalah, self::Proposal => 3,
            self::LaporanPraktikum, self::LaporanPkl => 5,
        };
    }

    /**
     * Required source data (brief.facts) the AI may only narrate, keyed as stored. list = one item per line.
     *
     * @return array<string, array{label: string, list: bool}>
     */
    public function factFields(): array
    {
        return match ($this) {
            self::LaporanPraktikum => [
                'experiment_title' => ['label' => 'Judul percobaan', 'list' => false],
                'objective' => ['label' => 'Tujuan', 'list' => false],
                'materials' => ['label' => 'Alat dan bahan', 'list' => true],
                'steps' => ['label' => 'Langkah kerja', 'list' => true],
                'observations' => ['label' => 'Data hasil pengamatan', 'list' => false],
            ],
            self::LaporanPkl => [
                'institution' => ['label' => 'Nama instansi', 'list' => false],
                'division' => ['label' => 'Divisi/bagian', 'list' => false],
                'period' => ['label' => 'Periode', 'list' => false],
                'activities' => ['label' => 'Daftar kegiatan', 'list' => true],
            ],
            default => [],
        };
    }

    /**
     * Heading shown on the cover page.
     */
    public function coverLabel(): string
    {
        return match ($this) {
            self::Makalah => 'MAKALAH',
            self::LaporanPraktikum => 'LAPORAN PRAKTIKUM',
            self::LaporanPkl => 'LAPORAN PKL',
            self::Proposal => 'PROPOSAL',
        };
    }
}
