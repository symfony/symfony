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

use Symfony\Component\Messenger\Envelope;

/**
 * Stamp applied when a messages needs to be redelivered.
 */
final class RedeliveryStamp implements StampInterface
{
    private int $retryCount;
    private \DateTimeInterface $redeliveredAt;

    public function __construct(int $retryCount, \DateTimeInterface $redeliveredAt = null)
    {
        $this->retryCount = $retryCount;
        $this->redeliveredAt = $redeliveredAt ?? new \DateTimeImmutable();
    }

    public static function getRetryCountFromEnvelope(Envelope $envelope): int
    {
        /** @var self|null $retryMessageStamp */
        $retryMessageStamp = $envelope->last(self::class);

        return $retryMessageStamp ? $retryMessageStamp->getRetryCount() : 0;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getRedeliveredAt(): \DateTimeInterface
    {
        return $this->redeliveredAt;
    }
}
