<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class TestServiceSubscriberParent implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    public function publicFunction1(): SomeClass
    {
    }

    #[SubscribedService]
    private function testDefinition1(): TestDefinition1
    {
        return $this->container->get(__METHOD__);
    }
}
