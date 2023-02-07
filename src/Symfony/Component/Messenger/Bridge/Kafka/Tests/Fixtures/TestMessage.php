<?php

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures;

class TestMessage
{
    public function __construct(
        public ?string $data = null
    ){
    }
}
