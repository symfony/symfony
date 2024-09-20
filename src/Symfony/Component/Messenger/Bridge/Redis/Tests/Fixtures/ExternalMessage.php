<?php

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures;

class ExternalMessage
{
    private array $bar = [];

    public function __construct(
        private string $foo,
    ) {
    }

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function setBar(array $bar): self
    {
        $this->bar = $bar;

        return $this;
    }

    public function getBar(): array
    {
        return $this->bar;
    }
}
