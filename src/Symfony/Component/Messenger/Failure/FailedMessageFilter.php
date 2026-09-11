<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Selects failed messages by class name and by the time they failed.
 *
 * Both bounds of the time window are inclusive.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class FailedMessageFilter
{
    public function __construct(
        public readonly ?string $class = null,
        public readonly ?\DateTimeImmutable $failedAfter = null,
        public readonly ?\DateTimeImmutable $failedBefore = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->class && null === $this->failedAfter && null === $this->failedBefore;
    }

    public function matches(Envelope $envelope): bool
    {
        if (null !== $this->class && $this->class !== $envelope->getMessage()::class) {
            return false;
        }

        if (null === $this->failedAfter && null === $this->failedBefore) {
            return true;
        }

        // messages that were never redelivered have no known failure time, so no time window can select them
        if (null === $failedAt = $envelope->last(RedeliveryStamp::class)?->getRedeliveredAt()) {
            return false;
        }

        return (null === $this->failedAfter || $failedAt >= $this->failedAfter)
            && (null === $this->failedBefore || $failedAt <= $this->failedBefore);
    }
}
