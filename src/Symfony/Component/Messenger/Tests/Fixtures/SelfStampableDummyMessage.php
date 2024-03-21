<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Message\SelfStampableInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class SelfStampableDummyMessage implements DummyMessageInterface, SelfStampableInterface
{
    public function __construct(private string $message)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getStamps(): array
    {
        return [new DelayStamp(1)];
    }
}
