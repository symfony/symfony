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
        // the provided execution time includes jitter, try to get back to the actual time
        $runWithoutJitter = $run->sub(new \DateInterval(\sprintf('PT%sS', $this->maxSeconds)));

        if (!$nextRun = $this->trigger->getNextRunDate($runWithoutJitter)) {
            return null;
        }

        // if we get the run that just ran, proceed to the next one
        while ($nextRun <= $run) {
            if (!$advanced = $this->trigger->getNextRunDate($nextRun)) {
                return null;
            }

            // the decorated trigger no longer advances: stop to avoid an infinite loop
            if ($advanced <= $nextRun) {
                break;
            }

            $nextRun = $advanced;
        }

        return $nextRun->add(new \DateInterval(\sprintf('PT%sS', random_int(0, $this->maxSeconds))));
    }
}
