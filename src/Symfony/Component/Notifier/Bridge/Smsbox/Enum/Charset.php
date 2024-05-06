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

enum Charset: string
{
    case Iso1 = 'iso-8859-1';
    case Iso15 = 'iso-8859-15';
    case Utf8 = 'utf-8';
}
