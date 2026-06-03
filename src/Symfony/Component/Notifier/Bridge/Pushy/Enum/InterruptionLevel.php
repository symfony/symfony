<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushy\Enum;

/**
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
enum InterruptionLevel: string
{
    case ACTIVE = 'active';
    case CRITICAL = 'critical';
    case PASSIVE = 'passive';
    case TIME_SENSITIVE = 'time-sensitive';
}
