<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Enum;

use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyInterface;

enum DummyBackedEnumWithInterface: string implements DummyInterface
{
    case ONE = 'one';
}
