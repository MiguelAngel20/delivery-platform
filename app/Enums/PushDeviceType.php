<?php

namespace App\Enums;

enum PushDeviceType: string
{
    case Web = 'web';
    case Android = 'android';
    case Ios = 'ios';
    case Unknown = 'unknown';
}
