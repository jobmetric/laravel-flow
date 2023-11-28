<?php

namespace JobMetric\Flow\Enums;

/**
 * @method static START()
 * @method static end()
 * @method static state()
 */
enum TableFlowStateFieldTypeEnum : string {
    use EnumToArray;
    case START = "start";
    case END = "end";
    case MIDDLE = "middle";
}
