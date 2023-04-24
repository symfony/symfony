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
class DatePeriodTrigger implements TriggerInterface
{
    public function __construct(
        private readonly \DatePeriod $period,
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s, %s',
            $this->period->getStartDate()->format(\DateTimeInterface::ATOM),
            $this->period->getEndDate()?->format(\DateTimeInterface::ATOM) ?? '(inf)',
            '-',
        );
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        $iterator = $this->period->getIterator();
        while ($run >= $next = $iterator->current()) {
            $iterator->next();
            if (!$iterator->valid()) {
                return null;
            }
        }

        return $next;
    }
}
