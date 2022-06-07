<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class TestServiceSubscriberChild extends TestServiceSubscriberParent
{
    use ServiceSubscriberTrait;
    use TestServiceSubscriberTrait;

    #[SubscribedService]
    private TestDefinition1 $testDefinition1;

    #[SubscribedService]
    private ?TestDefinition2 $testDefinition2;

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
