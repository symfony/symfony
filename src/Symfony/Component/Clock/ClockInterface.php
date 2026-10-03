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

use Psr\Clock\ClockInterface as PsrClockInterface;

/**
 * Returns the current time and sleeps, in a way that tests can mock.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ClockInterface extends PsrClockInterface
{
    public function sleep(float|int $seconds): void;

    public function withTimeZone(\DateTimeZone|string $timezone): static;
}
