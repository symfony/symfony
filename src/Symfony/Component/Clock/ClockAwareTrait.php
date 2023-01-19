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
use Symfony\Contracts\Service\Attribute\Required;

/**
 * A trait to help write time-sensitive classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait ClockAwareTrait
{
    private readonly ClockInterface $clock;

    #[Required]
    public function setClock(ClockInterface $clock): void
    {
        $this->clock = $clock;
    }

    protected function now(): \DateTimeImmutable
    {
        return ($this->clock ??= new Clock())->now();
    }
}
