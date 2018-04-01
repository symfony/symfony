<?php

namespace Symphony\Component\DependencyInjection\Tests\Fixtures;

use Symphony\Component\DependencyInjection\ServiceSubscriberInterface;

class TestServiceSubscriber implements ServiceSubscriberInterface
{
    public function __construct($container)
    {
    }

    public static function getSubscribedServices()
    {
        return array(
            __CLASS__,
            '?'.CustomDefinition::class,
            'bar' => CustomDefinition::class,
            'baz' => '?'.CustomDefinition::class,
        );
    }
}
