<?php

namespace App\Enums;

enum DocumentType: string
{
    case Makalah = 'makalah';
    case LaporanPraktikum = 'laporan_praktikum';
    case LaporanPkl = 'laporan_pkl';
    case Proposal = 'proposal';

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
