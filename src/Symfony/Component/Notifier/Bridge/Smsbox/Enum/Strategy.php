<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Enum;

enum Strategy: int
{
    case Private = 1;
    case Notification = 2;
    case NotMarketingGroup = 3;
    case Marketing = 4;
}
