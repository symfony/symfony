<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TaggedDummyHandler
{
    public function __invoke(DummyMessage $message)
    {
    }

    #[AsMessageHandler]
    public function handleSecondMessage(SecondMessage $message)
    {
    }

    #[AsMessageHandler(fromTransport: 'a')]
    #[AsMessageHandler(fromTransport: 'b')]
    public function handleThirdMessage(ThirdMessage $message): void
    {
    }
}
