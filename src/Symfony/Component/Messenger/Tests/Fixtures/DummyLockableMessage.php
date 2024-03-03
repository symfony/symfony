<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Lock\Key;
use Symfony\Component\Messenger\Message\LockableMessage;

class DummyLockableMessage implements DummyMessageInterface, LockableMessage
{
    private string $message;

    private ?Key $key;

    public function __construct(string $message, ?Key $key)
    {
        $this->message = $message;
        $this->key = $key;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getKey(): ?Key
    {
        return $this->key;
    }
}
