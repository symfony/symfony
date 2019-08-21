<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Contracts\Service\ServiceSubscriberInterface;

class TestServiceSubscriber implements ServiceSubscriberInterface
{
    public function __construct($container)
    {
    }

    public static function getSubscribedServices(): array
    {
        return [
            __CLASS__,
            '?'.CustomDefinition::class,
            'bar' => CustomDefinition::class,
            'baz' => '?'.CustomDefinition::class,
        ];
    }
}
