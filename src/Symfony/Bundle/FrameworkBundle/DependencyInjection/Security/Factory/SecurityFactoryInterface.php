<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;

interface SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $providerIds, $defaultEntryPoint);

    public function getPosition();

    public function getKey();
}
