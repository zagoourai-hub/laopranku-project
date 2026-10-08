<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Structuring = 'structuring';
    case Outlining = 'outlining';
    case OutlineReady = 'outline_ready';
    case Generating = 'generating';
    case Ready = 'ready';
    case Exported = 'exported';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Structuring => 'Menyusun struktur',
            self::Outlining => 'Menyusun outline',
            self::OutlineReady => 'Outline siap',
            self::Generating => 'Menulis isi',
            self::Ready => 'Siap',
            self::Exported => 'Diekspor',
        };
    }

    /**
     * Flux badge color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Ready, self::Exported => 'green',
            default => 'amber',
        };
    }
}
