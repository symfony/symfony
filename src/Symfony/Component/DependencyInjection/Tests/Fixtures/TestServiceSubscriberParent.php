<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberTrait;

class TestServiceSubscriberParent implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    private function testDefinition1(): TestDefinition1
    {
        return $this->container->get(__METHOD__);
    }
}
