<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Transport;

use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
class KafkaTransport implements TransportInterface
{
    private $logger;
    private $serializer;
    private $rdKafkaFactory;
    private $options;

    /** @var KafkaSender */
    private $sender;

    /** @var KafkaReceiver */
    private $receiver;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RdKafkaFactory $rdKafkaFactory,
        array $options
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->rdKafkaFactory = $rdKafkaFactory;
        $this->options = $options;
    }

    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    private function getSender(): KafkaSender
    {
        return $this->sender ?? $this->sender = new KafkaSender(
            $this->logger,
            $this->serializer,
            $this->rdKafkaFactory,
            $this->buildConf($this->options['conf'], $this->options['producer']['conf'] ?? []),
            $this->options['producer']
        );
    }

    private function getReceiver(): KafkaReceiver
    {
        return $this->receiver ?? $this->receiver = new KafkaReceiver(
            $this->logger,
            $this->serializer,
            $this->rdKafkaFactory,
            $this->buildConf($this->options['conf'], $this->options['consumer']['conf'] ?? []),
            $this->options['consumer']
        );
    }

    private function buildConf(array $baseConf, array $specificConf): KafkaConf
    {
        $conf = new KafkaConf();
        $confOptions = array_merge($baseConf, $specificConf);

        foreach ($confOptions as $option => $value) {
            $conf->set($option, $value);
        }

        return $conf;
    }
}
