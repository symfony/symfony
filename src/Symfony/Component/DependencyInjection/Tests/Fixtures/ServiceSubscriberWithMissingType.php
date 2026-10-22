<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class ServiceSubscriberWithMissingType implements ServiceSubscriberInterface
{
    public function __construct(public ContainerInterface $container)
    {
    }

    public static function getSubscribedServices(): array
    {
        return ['counter' => \Countable::class];
    }
}
