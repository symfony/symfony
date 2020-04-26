<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class RegisterLdapLocatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->setDefinition('security.ldap_locator', new Definition(ServiceLocator::class));

        $locators = [];
        foreach ($container->findTaggedServiceIds('ldap') as $serviceId => $tags) {
            $locators[$serviceId] = new ServiceClosureArgument(new Reference($serviceId));
        }

        $definition->addArgument($locators);
    }
}
