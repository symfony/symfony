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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Removes from container unneeded services registered by 3rd party bundles
 *
 * @author Dariusz Górecki <darek.krk@gmail.com>
 */
class ContainerCleanerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach (explode('|', $container->getParameter('framework.services_to_remove')) as $serviceID) {
            if ($container->has($serviceID)) {
                $container->removeDefinition($serviceID);
            }
        }
    }
}
