<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\Transport;

#[Transport('message_attribute_sender')]
class DummyMessageWithAttributeAndInterface implements DummyMessageInterfaceWithAttribute
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
