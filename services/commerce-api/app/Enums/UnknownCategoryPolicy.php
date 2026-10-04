<?php

namespace App\Enums;

enum UnknownCategoryPolicy: string
{
    case Create = 'create';
    case Uncategorized = 'uncategorized';
    case Reject = 'reject';
}
