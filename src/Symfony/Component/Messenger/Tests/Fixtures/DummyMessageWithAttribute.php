<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\Transport;

#[Transport('message_attribute_sender')]
#[CustomTransport]
class DummyMessageWithAttribute implements DummyMessageInterface
{
    private $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

#[\Attribute(\Attribute::TARGET_CLASS)]
class CustomTransport extends Transport
{
    public function __construct()
    {
        parent::__construct('message_attribute_sender_2');
    }
}
