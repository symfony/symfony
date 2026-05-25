<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Execution\Message\DeferredBatchMessage;

/**
 * @internal
 */
final class DeferredBatchMessageQueue
{
    /** @var \SplObjectStorage<object, DeferredBatchMessage>|null */
    private ?\SplObjectStorage $messages = null;

    public function hasPending(): bool
    {
        return null !== $this->messages;
    }

    public function add(object $batchHandler, string $transportName, Envelope $envelope, bool &$acked, float $queuedAt): void
    {
        $this->messages ??= new \SplObjectStorage();
        $this->messages[$batchHandler] = new DeferredBatchMessage($transportName, $envelope, $acked, $queuedAt);
    }

    /**
     * @return \SplObjectStorage<object, DeferredBatchMessage>
     */
    public function popFlushable(bool|float $force, float $now): \SplObjectStorage
    {
        if (!$this->messages) {
            return new \SplObjectStorage();
        }

        if (\is_bool($force)) {
            $messages = $this->messages;
            $this->messages = null;

            return $messages;
        }

        $remaining = new \SplObjectStorage();
        $flushable = new \SplObjectStorage();

        foreach ($this->messages as $handler) {
            if ($force <= $now - $this->messages[$handler]->queuedAt) {
                $flushable[$handler] = $this->messages[$handler];
            } else {
                $remaining[$handler] = $this->messages[$handler];
            }
        }

        $this->messages = $remaining->count() ? $remaining : null;

        return $flushable;
    }
}
