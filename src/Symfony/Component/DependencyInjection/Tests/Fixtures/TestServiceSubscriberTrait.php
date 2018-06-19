<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

trait TestServiceSubscriberTrait
{
    private function testDefinition3(): TestDefinition3
    {
        return $this->container->get(__CLASS__.'::'.__FUNCTION__);
    }
}
