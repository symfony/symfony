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
use Symfony\Component\Scheduler\Trigger\DateIntervalTrigger;
use Symfony\Component\Scheduler\Trigger\JitterTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * @experimental
 */
final class RecurringMessage
{
    private string $id;

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
    public static function every(string $frequency, object $message, string|\DateTimeImmutable $from = new \DateTimeImmutable(), string|\DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01')): self
    {
        if (false === $interval = \DateInterval::createFromDateString($frequency)) {
            throw new InvalidArgumentException(sprintf('Frequency "%s" cannot be parsed.', $frequency));
        }

        return new self(new DateIntervalTrigger($interval, $from, $until), $message);
    }

    public static function cron(string $expression, object $message): self
    {
        if (!str_contains($expression, '#')) {
            return new self(CronExpressionTrigger::fromSpec($expression), $message);
        }

        if (!$message instanceof \Stringable) {
            throw new InvalidArgumentException('A message must be stringable to use "hashed" cron expressions.');
        }

        return new self(CronExpressionTrigger::fromSpec($expression, (string) $message), $message);
    }

    public static function trigger(TriggerInterface $trigger, object $message): self
    {
        return new self($trigger, $message);
    }

    public function withJitter(int $maxSeconds = 60): self
    {
        return new self(new JitterTrigger($this->trigger, $maxSeconds), $this->message);
    }

    /**
     * Unique identifier for this message's context.
     */
    public function getId(): string
    {
        if (isset($this->id)) {
            return $this->id;
        }

        try {
            $message = $this->message instanceof \Stringable ? (string) $this->message : serialize($this->message);
        } catch (\Exception) {
            $message = '';
        }

        return $this->id = hash('crc32c', implode('', [
            $this->message::class,
            $message,
            $this->trigger::class,
            (string) $this->trigger,
        ]));
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
