<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * CommandPass.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class CommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $commandServices = $container->findTaggedServiceIds('console.command');

        foreach ($commandServices as $serviceId => $tag) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($serviceId)->getClass());
            $r = new \ReflectionClass($class);
            if (!$r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command')) {
                throw new \InvalidArgumentException(sprintf('The service "%s" tagged "console.command" must be a subclass of "Symfony\\Component\\Console\\Command\\Command"', $serviceId));
            }
            $alias = 'console.command.'.strtolower(str_replace('\\', '_', $class));
            $container->setAlias($alias, $serviceId);
        }

        $container->setParameter('console.commands', array_keys($commandServices));
    }
}
