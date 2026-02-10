<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
interface TaggedDummyHandlerInterface
{
}
