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

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;

/**
 * @experimental
 */
class DateIntervalTrigger extends DatePeriodTrigger
{
    public function __construct(
        string|int|\DateInterval $interval,
        string|\DateTimeImmutable $from = new \DateTimeImmutable(),
        string|\DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01'),
    ) {
        if (\is_string($from)) {
            $from = new \DateTimeImmutable($from);
        }
        if (\is_string($until)) {
            $until = new \DateTimeImmutable($until);
        }
        try {
            if (\is_int($interval)) {
                $interval = new \DateInterval('PT'.$interval.'S');
            } elseif (\is_string($interval)) {
                if ('P' === ($interval[0] ?? '')) {
                    $interval = new \DateInterval($interval);
                } elseif (ctype_digit($interval)) {
                    $interval = new \DateInterval('PT'.$interval.'S');
                } else {
                    $interval = \DateInterval::createFromDateString($interval);
                }
            }
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Invalid interval "%s": ', $interval).$e->getMessage(), 0, $e);
        }

        parent::__construct(new \DatePeriod($from, $interval, $until));
    }
}
