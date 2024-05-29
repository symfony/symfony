<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

class DummyMessage implements DummyMessageInterface
{
    public function __construct(
        private string $message,
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
