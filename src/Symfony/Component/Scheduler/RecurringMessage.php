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
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\JitterTrigger;
use Symfony\Component\Scheduler\Trigger\MessageProviderInterface;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\StaticMessageProvider;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

final class RecurringMessage implements MessageProviderInterface
{
    private string $id;

    private function __construct(
        private readonly TriggerInterface $trigger,
        private readonly MessageProviderInterface $provider,
    ) {
    }

    /**
     * Sets the trigger frequency.
     *
     * Supported frequency formats:
     *
     *  * An integer to define the frequency as a number of seconds;
     *  * An ISO 8601 duration format;
     *  * A relative date format as supported by \DateInterval;
     *  * A \DateInterval instance.
     *
     * @param MessageProviderInterface|object $message A message provider that yields messages or a static message that will be dispatched on every trigger
     *
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations
     * @see https://php.net/datetime.formats#datetime.formats.relative
     */
    public static function every(string|int|\DateInterval $frequency, object $message, string|\DateTimeImmutable|null $from = null, string|\DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01')): self
    {
        return self::trigger(new PeriodicalTrigger($frequency, $from, $until), $message);
    }

    /**
     * @param MessageProviderInterface|object $message A message provider that yields messages or a static message that will be dispatched on every trigger
     */
    public static function cron(string $expression, object $message, \DateTimeZone|string|null $timezone = null): self
    {
        if (!str_contains($expression, '#')) {
            return self::trigger(CronExpressionTrigger::fromSpec($expression, null, $timezone), $message);
        }

        if (!$message instanceof \Stringable) {
            throw new InvalidArgumentException('A message must be stringable to use "hashed" cron expressions.');
        }

        return self::trigger(CronExpressionTrigger::fromSpec($expression, (string) $message, $timezone), $message);
    }

    /**
     * @param MessageProviderInterface|object $message A message provider that yields messages or a static message that will be dispatched on every trigger
     */
    public static function trigger(TriggerInterface $trigger, object $message): self
    {
        if ($message instanceof MessageProviderInterface) {
            return new self($trigger, $message);
        }

        $description = $message::class;
        if ($message instanceof \Stringable) {
            try {
                $description .= " ($message)";
            } catch (\Exception) {
            }
        }

        return new self($trigger, new StaticMessageProvider([$message], strtr(substr(base64_encode(hash('xxh128', serialize($message), true)), 0, 7), '/+', '._'), $description));
    }

    public function withJitter(int $maxSeconds = 60): self
    {
        return new self(new JitterTrigger($this->trigger, $maxSeconds), $this->provider);
    }

    /**
     * Unique identifier for this message's context.
     */
    public function getId(): string
    {
        if (isset($this->id)) {
            return $this->id;
        }

        return $this->id = hash('crc32c', implode('', [
            $this->provider::class,
            $this->provider->getId(),
            $this->trigger::class,
            (string) $this->trigger,
        ]));
    }

    public function getMessages(MessageContext $context): iterable
    {
        return $this->provider->getMessages($context);
    }

    public function getProvider(): MessageProviderInterface
    {
        return $this->provider;
    }

    public function getTrigger(): TriggerInterface
    {
        return $this->trigger;
    }
}
