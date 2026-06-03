<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

class DummyMessage implements DummyMessageInterface
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
