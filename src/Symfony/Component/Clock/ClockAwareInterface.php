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

use Psr\Clock\ClockInterface;

/**
 * An interface to help write time-sensitive classes.
 *
 * @author Sergii Dolgushev <p@tchwork.com>
 */
interface ClockAwareInterface
{
    public function setClock(ClockInterface $clock): void;

    public function now(): DatePoint;
}
