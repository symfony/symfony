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

enum Mode: string
{
    case Standard = 'Standard';
    case Expert = 'Expert';
    case Response = 'Reponse';
}
