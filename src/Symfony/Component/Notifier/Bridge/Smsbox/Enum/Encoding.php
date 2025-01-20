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

enum Encoding: string
{
    case Default = 'default';
    case Unicode = 'unicode';
    case Auto = 'auto';
}
