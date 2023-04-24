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

/**
 * @experimental
 */
final class ExcludeTimeTrigger implements TriggerInterface
{
    public function __construct(
        private readonly TriggerInterface $inner,
        private readonly \DateTimeImmutable $from,
        private readonly \DateTimeImmutable $until,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('%s, from: %s, until: %s', $this->inner, $this->from->format(\DateTimeInterface::ATOM), $this->until->format(\DateTimeInterface::ATOM));
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        $nextRun = $this->inner->getNextRunDate($run);
        if ($nextRun >= $this->from && $nextRun <= $this->until) {
            return $this->inner->getNextRunDate($this->until);
        }

        return $nextRun;
    }
}
