<?php

namespace Symfony\Component\Translation\Tests\DependencyInjection\fixtures;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ServiceSubscriber implements ServiceSubscriberInterface
{
    public function __construct(ContainerInterface $container)
    {
    }

    public static function getSubscribedServices(): array
    {
        return ['translator' => TranslatorInterface::class];
    }
}
