<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\Transport;

#[Transport('my_common_sender')]
interface DummyMessageInterfaceWithAttribute
{
}
