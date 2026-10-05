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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\BatchCollector;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Defers sending a message until all the messages of its batch went through the bus.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 *
 * @internal
 */
final class BatchStamp implements NonSendableStampInterface
{
    public function __construct(
        private BatchCollector $collector,
        private int $id,
    ) {
    }

    /**
     * @param array<SenderInterface> $senders
     *
     * @return bool Whether sending is deferred, which is not the case anymore once the batch is over
     */
    public function defer(Envelope $envelope, array $senders, ?EventDispatcherInterface $eventDispatcher): bool
    {
        return $this->collector->defer($this->id, $envelope, $senders, $eventDispatcher);
    }
}
