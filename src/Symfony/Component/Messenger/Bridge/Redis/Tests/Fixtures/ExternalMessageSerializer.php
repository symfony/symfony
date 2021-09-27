<?php

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class ExternalMessageSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        $message = new ExternalMessage($encodedEnvelope['foo']);
        $message->setBar($encodedEnvelope['bar']);

        return new Envelope($message);
    }

    public function encode(Envelope $envelope): array
    {
        return [
            'body' => $envelope->getMessage(),
            'headers' => [],
        ];
    }
}
