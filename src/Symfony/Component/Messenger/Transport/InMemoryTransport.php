<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Transport that stays in memory. Useful for testing purpose.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransport implements TransportInterface, ResetInterface
{
    /**
     * @var Envelope[]
     */
    private $sent = [];

    /**
     * @var Envelope[]
     */
    private $acknowledged = [];

    /**
     * @var Envelope[]
     */
    private $rejected = [];

    /**
     * @var Envelope[]
     */
    private $queue = [];

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return array_values($this->queue);
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->acknowledged[] = $envelope;
        $id = spl_object_hash($envelope->getMessage());
        unset($this->queue[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $envelope;
        $id = spl_object_hash($envelope->getMessage());
        unset($this->queue[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $this->sent[] = $envelope;
        $id = spl_object_hash($envelope->getMessage());
        $this->queue[$id] = $envelope;

        return $envelope;
    }

    public function reset()
    {
        $this->sent = $this->queue = $this->rejected = $this->acknowledged = [];
    }

    /**
     * @return Envelope[]
     */
    public function getAcknowledged(): array
    {
        return $this->acknowledged;
    }

    /**
     * @return Envelope[]
     */
    public function getRejected(): array
    {
        return $this->rejected;
    }

    /**
     * @return Envelope[]
     */
    public function getSent(): array
    {
        return $this->sent;
    }
}
