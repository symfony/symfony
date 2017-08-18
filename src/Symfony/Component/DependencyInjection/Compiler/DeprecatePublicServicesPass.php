<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal to be removed in 4.0
 */
class DeprecatePublicServicesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $services = array();
        foreach ($container->findTaggedServiceIds('deprecated.public') as $id => $tags) {
            $definition = $container->getDefinition($id);
            if ($definition->isPublic()) {
                throw new LogicException(sprintf('Mark the service "%s" private before deprecating public access to it.', $id));
            }

            $definition->clearTag('deprecated.public');
            $services[$id] = new Reference($id);
        }

        if (!$services) {
            return;
        }

        $container->getDefinition(ServiceLocatorTagPass::register($container, $services))
            ->setPublic(true)
            ->setDeprecated('The service "%service_id%" is internal and deprecated since Symfony 3.4 and will be removed in Symfony 4.0');
    }
}
