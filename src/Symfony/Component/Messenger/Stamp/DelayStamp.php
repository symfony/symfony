<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Apply this stamp to delay delivery of your message on a transport.
 */
final class DelayStamp implements StampInterface
{
    /**
     * @param int $delay The delay in milliseconds
     */
    public function __construct(
        private int $delay,
    ) {
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public static function delayFor(\DateInterval $interval): self
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(self::getMilliseconds($now, $now->add($interval)));
    }

    public static function delayUntil(\DateTimeInterface $dateTime): self
    {
        return new self(self::getMilliseconds(\DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', microtime(true))), $dateTime));
    }

    private static function getMilliseconds(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) round(($to->getTimestamp() - $from->getTimestamp()) * 1000 + ((int) $to->format('u') - (int) $from->format('u')) / 1000);
    }
}
