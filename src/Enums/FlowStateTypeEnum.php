<?php

namespace JobMetric\Flow\Enums;

use JobMetric\PackageCore\Enums\EnumMacros;

/**
 * @method static START()
 * @method static END()
 * @method static STATE()
 */
enum FlowStateTypeEnum: string
{
    use EnumMacros;

    case START = "start";
    case END = "end";
    case STATE = "state";
}
