<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\Attribute\SubscribedService;

trait TestServiceSubscriberTrait
{
    protected function protectedFunction1(): SomeClass
    {
    }

    #[SubscribedService]
    private function testDefinition4(): TestDefinition3
    {
        return $this->container->get(__CLASS__.'::'.__FUNCTION__);
    }
}
