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

enum Spacing: string
{
    case Default = 'Default';
    case None = 'None';
    case Small = 'Small';
    case Medium = 'Medium';
    case Large = 'Large';
    case ExtraLarge = 'ExtraLarge';
    case Padding = 'Padding';
}
