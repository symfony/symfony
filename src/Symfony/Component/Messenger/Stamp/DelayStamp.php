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
        $end = $now->add($interval);

        return new self(($end->getTimestamp() - $now->getTimestamp()) * 1000);
    }

    public static function delayUntil(\DateTimeInterface $dateTime): self
    {
        return new self(($dateTime->getTimestamp() - time()) * 1000);
    }
}
