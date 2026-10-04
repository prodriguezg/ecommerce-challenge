<?php

namespace App\Enums;

enum SettingType: string
{
    case Integer = 'integer';
    case Boolean = 'boolean';
    case String = 'string';
}
