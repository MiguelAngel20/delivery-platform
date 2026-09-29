<?php

namespace App\Enums;

enum AdminAbility: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
