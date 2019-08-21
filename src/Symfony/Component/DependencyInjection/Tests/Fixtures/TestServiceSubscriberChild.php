<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\ServiceSubscriberTrait;

class TestServiceSubscriberChild extends TestServiceSubscriberParent
{
    use ServiceSubscriberTrait;
    use TestServiceSubscriberTrait;

    private function testDefinition2(): TestDefinition2
    {
        return $this->container->get(__METHOD__);
    }

    private function invalidDefinition(): InvalidDefinition
    {
        return $this->container->get(__METHOD__);
    }

    private function privateFunction1(): string
    {
    }

    private function privateFunction2(): string
    {
    }
}
