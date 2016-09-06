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

/**
 * AddConsoleCommandPass.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AddConsoleCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $commandServices = $container->findTaggedServiceIds('console.command');
        $serviceIds = array();

        foreach ($commandServices as $id => $tags) {
            $definition = $container->getDefinition($id);

            if ($definition->isAbstract()) {
                throw new \InvalidArgumentException(sprintf('The service "%s" tagged "console.command" must not be abstract.', $id));
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            if (!is_subclass_of($class, 'Symfony\\Component\\Console\\Command\\Command')) {
                if (!class_exists($class, false)) {
                    throw new \InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                }

                throw new \InvalidArgumentException(sprintf('The service "%s" tagged "console.command" must be a subclass of "Symfony\\Component\\Console\\Command\\Command".', $id));
            }
            $container->setAlias($serviceId = 'console.command.'.strtolower(str_replace('\\', '_', $class)), $id);
            $serviceIds[] = $definition->isPublic() ? $id : $serviceId;
        }

        $container->setParameter('console.command.ids', $serviceIds);
    }
}
