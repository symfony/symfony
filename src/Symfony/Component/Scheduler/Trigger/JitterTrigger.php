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
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class JitterTrigger extends AbstractDecoratedTrigger
{
    /**
     * @param positive-int $maxSeconds
     */
    public function __construct(private readonly TriggerInterface $trigger, private readonly int $maxSeconds = 60)
    {
        parent::__construct($trigger);
    }

    public function __toString(): string
    {
        return \sprintf('%s with 0-%d second jitter', $this->trigger, $this->maxSeconds);
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        if (!$nextRun = $this->trigger->getNextRunDate($run)) {
            return null;
        }

        return $nextRun->add(new \DateInterval(\sprintf('PT%sS', random_int(0, $this->maxSeconds))));
    }
}
