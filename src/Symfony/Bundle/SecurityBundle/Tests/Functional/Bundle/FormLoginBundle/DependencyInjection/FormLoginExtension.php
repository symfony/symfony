<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Reference;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class FormLoginExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $container
            ->register('localized_form_failure_handler', 'Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Security\LocalizedFormFailureHandler')
            ->addArgument(new Reference('router'))
        ;
    }
}