<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard\Microsoft;

enum IconSize: string
{
    case XXSmall = 'xxSmall';
    case XSmall = 'xSmall';
    case Small = 'Small';
    case Medium = 'Medium';
    case Standard = 'Standard';
    case Large = 'Large';
    case XLarge = 'xLarge';
    case XXLarge = 'xxLarge';
}
