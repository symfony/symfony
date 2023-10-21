<?php

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures;

class DummyMessage
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
