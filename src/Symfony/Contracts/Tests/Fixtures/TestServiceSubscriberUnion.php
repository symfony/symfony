<?php

namespace Symfony\Contracts\Tests\Fixtures;

use Symfony\Contracts\Service\ServiceSubscriberTrait;

class TestServiceSubscriberUnion
{
    use ServiceSubscriberTrait;

    private function method1(): Service1
    {
        return $this->container->get(__METHOD__);
    }

    private function method2(): Service1|Service2
    {
        return $this->container->get(__METHOD__);
    }

    private function method3(): Service1|Service2|null
    {
        return $this->container->get(__METHOD__);
    }
}
