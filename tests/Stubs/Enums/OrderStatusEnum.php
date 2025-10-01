<?php

namespace JobMetric\Flow\Tests\Stubs\Enums;

use JobMetric\PackageCore\Enums\EnumMacros;

/**
 * @method static EDITABLE()
 * @method static PENDING()
 * @method static NEED_CONFIRM()
 * @method static EXPIRED()
 * @method static PAID()
 * @method static CANCELED()
 */
enum OrderStatusEnum: string
{
    use EnumMacros;

    case EDITABLE = "editable";
    case PENDING = "pending";
    case NEED_CONFIRM = "need_confirm";
    case EXPIRED = "expired";
    case PAID = "paid";
    case CANCELED = "canceled";
}
