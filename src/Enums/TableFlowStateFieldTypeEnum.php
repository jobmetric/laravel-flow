<?php

namespace JobMetric\Flow\Enums;

use JobMetric\PackageCore\Enums\EnumMacros;

/**
 * @method static START()
 * @method static end()
 * @method static state()
 */
enum TableFlowStateFieldTypeEnum : string {
    use EnumMacros;

    case START = "start";
    case END = "end";
    case MIDDLE = "middle";
}
