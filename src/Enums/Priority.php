<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Enums;

enum Priority: string
{

    case HIGHEST = '1 (Highest)';
    case HIGH = '2 (High)';
    case NORMAL = '3 (Normal)';
    case LOW = '4 (Low)';
    case LOWEST = '5 (Lowest)';
}
