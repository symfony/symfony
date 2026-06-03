<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Message\DefaultStampsProviderInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class DefaultStampsProviderDummyMessage implements DummyMessageInterface, DefaultStampsProviderInterface
{
    public function __construct(private string $message)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDefaultStamps(): array
    {
        return [new DelayStamp(1)];
    }
}
