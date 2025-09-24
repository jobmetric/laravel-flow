<?php

namespace JobMetric\Flow\Enums;

use JobMetric\PackageCore\Enums\EnumMacros;

/**
 * @method static START()
 * @method static STATE()
 */
enum FlowStateTypeEnum: string
{
    use EnumMacros;

    case START = "start";
    case STATE = "state";
}
