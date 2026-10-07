<?php

namespace App\Enums;

enum DocumentType: string
{
    case Makalah = 'makalah';
    case LaporanPraktikum = 'laporan_praktikum';
    case LaporanPkl = 'laporan_pkl';
    case Proposal = 'proposal';
}
