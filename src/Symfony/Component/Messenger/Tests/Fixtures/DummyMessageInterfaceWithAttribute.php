<?php

namespace Symfony\Component\Messenger\Tests\Fixtures;

use Symfony\Component\Messenger\Attribute\Transport;

#[Transport('interface_attribute_sender')]
interface DummyMessageInterfaceWithAttribute
{
}
