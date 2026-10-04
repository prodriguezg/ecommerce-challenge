<?php

namespace App\Enums;

enum ImportMode: string
{
    case CreateOnly = 'create_only';
    case UpdateOnly = 'update_only';
    case Upsert = 'upsert';
}
