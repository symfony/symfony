<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;

class TestServiceSubscriber implements ServiceSubscriberInterface
{
    public function __construct($container)
    {
    }

    public static function getSubscribedServices()
    {
        return array(
            self::class,
            '?'.CustomDefinition::class,
            'bar' => CustomDefinition::class,
            'baz' => '?'.CustomDefinition::class,
        );
    }
}
