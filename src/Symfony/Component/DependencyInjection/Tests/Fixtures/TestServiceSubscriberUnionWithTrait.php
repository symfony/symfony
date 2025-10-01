<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class TestServiceSubscriberUnionWithTrait implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    #[SubscribedService]
    private function method1(): TestDefinition1|TestDefinition2|null
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    private function method2(): TestDefinition1|TestDefinition2
    {
        return $this->container->get(__METHOD__);
    }
}
