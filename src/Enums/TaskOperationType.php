<?php

namespace JobMetric\Flow\Enums;

use JobMetric\PackageCore\Enums\EnumMacros;

enum TaskOperationType: string
{
    use EnumMacros;

    case VALIDATION = 'validation';
    case RESTRICTION = 'restriction';
    case ACTION = 'action';
}
