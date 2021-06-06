<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures;

class TestMessage
{
    public $data;

    public function __construct(?string $data = null)
    {
        $this->data = $data;
    }
}
