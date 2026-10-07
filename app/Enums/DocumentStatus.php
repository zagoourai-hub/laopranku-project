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
}
