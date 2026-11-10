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

enum ContainerStyle: string
{
    case Default = 'default';
    case Emphasis = 'emphasis';
    case Good = 'good';
    case Attention = 'attention';
    case Warning = 'warning';
    case Accent = 'accent';
}
