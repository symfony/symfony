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
 *
 * @experimental in 4.3
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
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return $this->sent;
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->acknowledged[] = $envelope;
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $envelope;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $this->sent[] = $envelope;

        return $envelope;
    }

    public function reset()
    {
        $this->sent = $this->rejected = $this->acknowledged = [];
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
}
