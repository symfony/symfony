<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TaggedDummyHandlerWithCustomMethods
{
    public function handleDummyMessage(DummyMessage $message)
    {
    }

    public function handleSecondMessage(SecondMessage $message)
    {
    }
}
