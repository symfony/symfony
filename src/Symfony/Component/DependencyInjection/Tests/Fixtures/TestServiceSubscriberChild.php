<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class TestServiceSubscriberChild extends TestServiceSubscriberParent
{
    use ServiceSubscriberTrait;
    use TestServiceSubscriberTrait;

    #[SubscribedService]
    private function testDefinition2(): ?TestDefinition2
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService('custom_name')]
    private function testDefinition3(): TestDefinition3
    {
        return $this->container->get('custom_name');
    }

    #[SubscribedService]
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

    private function privateFunction3(): AnotherClass
    {
    }
}
