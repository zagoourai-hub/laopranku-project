<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateDocument;
use App\Mcp\Tools\ExportDocument;
use App\Mcp\Tools\GetDocument;
use App\Mcp\Tools\ListTemplates;
use App\Mcp\Tools\UpdateChapter;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Laporanku')]
#[Version('1.0.0')]
#[Instructions('Buat dan ubah dokumen makalah/laporan berformat sekolah atau kampus. Judul bab tidak boleh diawali nomor atau kata BAB; penomoran dibuat otomatis. Ekspor menghasilkan tautan unduhan sementara (10 menit).')]
class LaporankuServer extends Server
{
    protected array $tools = [
        ListTemplates::class,
        CreateDocument::class,
        GetDocument::class,
        UpdateChapter::class,
        ExportDocument::class,
    ];
}
