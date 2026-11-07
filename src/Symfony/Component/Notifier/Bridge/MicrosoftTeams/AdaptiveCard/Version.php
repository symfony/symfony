<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

enum Version: string
{
    case Version10 = '1.0';
    case Version11 = '1.1';
    case Version12 = '1.2';
    case Version13 = '1.3';
    case Version14 = '1.4';
    case Version15 = '1.5';
    case Version16 = '1.6';
}
