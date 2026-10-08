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
