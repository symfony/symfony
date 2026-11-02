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

enum FontSize: string
{
    case Default = 'Default';
    case Small = 'Small';
    case Medium = 'Medium';
    case Large = 'Large';
    case ExtraLarge = 'ExtraLarge';
}
