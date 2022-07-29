<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

final class OnceTrigger implements TriggerInterface
{
    public function __construct(
        private readonly \DateTimeImmutable $time,
    ) {
    }

    public function nextTo(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        return $run < $this->time ? $this->time : null;
    }
}
