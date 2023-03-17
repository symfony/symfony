<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * @experimental
 */
final class RecurringMessage
{
    private function __construct(
        private readonly TriggerInterface $trigger,
        private readonly object $message,
    ) {
    }

    /**
     * Uses a relative date format to define the frequency.
     *
     * @see https://php.net/datetime.formats.relative
     */
    public static function every(string $frequency, object $message, \DateTimeImmutable $from = new \DateTimeImmutable(), \DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01')): self
    {
        if (false === $interval = \DateInterval::createFromDateString($frequency)) {
            throw new InvalidArgumentException(sprintf('Frequency "%s" cannot be parsed.', $frequency));
        }

        return new self(PeriodicalTrigger::create($interval, $from, $until), $message);
    }

    public static function cron(string $expression, object $message): self
    {
        return new self(CronExpressionTrigger::fromSpec($expression), $message);
    }

    public static function trigger(TriggerInterface $trigger, object $message): self
    {
        return new self($trigger, $message);
    }

    public function getMessage(): object
    {
        return $this->message;
    }

    public function getTrigger(): TriggerInterface
    {
        return $this->trigger;
    }
}
