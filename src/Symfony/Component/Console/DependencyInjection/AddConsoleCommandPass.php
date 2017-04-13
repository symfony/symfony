<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\DependencyInjection;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Registers console commands.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AddConsoleCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $commandServices = $container->findTaggedServiceIds('console.command', true);
        $serviceIds = array();

        foreach ($commandServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->isSubclassOf(Command::class)) {
                throw new InvalidArgumentException(sprintf('The service "%s" tagged "console.command" must be a subclass of "%s".', $id, Command::class));
            }

            if (!$definition->isPublic()) {
                $serviceId = 'console.command.'.strtolower(str_replace('\\', '_', $class));
                if ($container->hasAlias($serviceId)) {
                    $serviceId = $serviceId.'_'.$id;
                }
                $container->setAlias($serviceId, $id);
                $id = $serviceId;
            }

            $serviceIds[] = $id;
        }

        $container->setParameter('console.command.ids', $serviceIds);
    }
}
