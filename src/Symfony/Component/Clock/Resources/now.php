<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Clock;

/**
 * Returns the current time as a DateTimeImmutable.
 *
 * Note that you should prefer injecting a ClockInterface or using
 * ClockAwareTrait when possible instead of using this function.
 */
function now(): \DateTimeImmutable
{
    return Clock::get()->now();
}
