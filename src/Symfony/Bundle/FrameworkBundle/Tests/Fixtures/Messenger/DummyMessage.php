<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger;

class DummyMessage implements DummyMessageInterface
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
