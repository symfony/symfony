<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TaggedDummyHandler
{
    public function __invoke(DummyMessage $message)
    {
    }
}
