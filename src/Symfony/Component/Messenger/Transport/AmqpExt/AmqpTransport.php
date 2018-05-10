<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AmqpTransport implements TransportInterface
{
    private $encoder;
    private $decoder;
    private $connection;
    private $receiver;
    private $sender;

    public function __construct(EncoderInterface $encoder, DecoderInterface $decoder, Connection $connection)
    {
        $this->encoder = $encoder;
        $this->decoder = $decoder;
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        ($this->receiver ?? $this->getReceiver())->receive($handler);
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        ($this->receiver ?? $this->getReceiver())->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): void
    {
        ($this->sender ?? $this->getSender())->send($envelope);
    }

    private function getReceiver()
    {
        return $this->receiver = new AmqpReceiver($this->decoder, $this->connection);
    }

    private function getSender()
    {
        return $this->sender = new AmqpSender($this->encoder, $this->connection);
    }
}
