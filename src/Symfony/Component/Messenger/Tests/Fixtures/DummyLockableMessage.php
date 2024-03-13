<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Message\LockableMessageInterface;

class DummyLockableMessage implements DummyMessageInterface, LockableMessageInterface
{
    private string $message;

    private ?string $key;

    public function __construct(string $message, ?string $key)
    {
        $this->message = $message;
        $this->key = $key;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function shouldBeReleasedBeforeHandlerCall(): bool
    {
        return false;
    }
}
