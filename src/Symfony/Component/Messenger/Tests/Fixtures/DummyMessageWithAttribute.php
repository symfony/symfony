<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: ['first_sender', 'second_sender'])]
class DummyMessageWithAttribute implements DummyMessageInterface
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
