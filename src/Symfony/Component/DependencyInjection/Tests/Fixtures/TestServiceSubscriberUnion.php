<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\ServiceSubscriberInterface;

class TestServiceSubscriberUnion implements ServiceSubscriberInterface
{
    public function __construct($container)
    {
    }

    public static function getSubscribedServices(): array
    {
        return [
            'string|'.TestDefinition2::class.'|'.TestDefinition1::class,
            'bar' => TestDefinition1::class.'|'.TestDefinition2::class,
            'baz' => '?'.TestDefinition1::class.'|'.TestDefinition2::class,
        ];
    }
}
