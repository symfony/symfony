<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class DummyReceiver implements ReceiverInterface
{
    private array $deliveriesOfEnvelopes;
    private array $acknowledgedEnvelopes = [];
    private array $rejectedEnvelopes = [];
    private int $acknowledgeCount = 0;
    private int $rejectCount = 0;

    /**
     * @param Envelope[][] $deliveriesOfEnvelopes
     */
    public function __construct(array $deliveriesOfEnvelopes)
    {
        $this->deliveriesOfEnvelopes = $deliveriesOfEnvelopes;
    }

    public function get(): iterable
    {
        $val = array_shift($this->deliveriesOfEnvelopes);

        return $val ?? [];
    }

    public function ack(Envelope $envelope): void
    {
        ++$this->acknowledgeCount;
        $this->acknowledgedEnvelopes[] = $envelope;
    }

    public function reject(Envelope $envelope): void
    {
        ++$this->rejectCount;
        $this->rejectedEnvelopes[] = $envelope;
    }

    public function getAcknowledgeCount(): int
    {
        return $this->acknowledgeCount;
    }

    public function getRejectCount(): int
    {
        return $this->rejectCount;
    }

    public function getAcknowledgedEnvelopes(): array
    {
        return $this->acknowledgedEnvelopes;
    }
}
