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
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TestServiceContainerWeakRefPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('test.private_services_locator')) {
            return;
        }

        $privateServices = array();
        $definitions = $container->getDefinitions();

        foreach ($definitions as $id => $definition) {
            if ($id && '.' !== $id[0] && (!$definition->isPublic() || $definition->isPrivate()) && !$definition->getErrors() && !$definition->isAbstract()) {
                $privateServices[$id] = new Reference($id, ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE);
            }
        }

        $aliases = $container->getAliases();

        foreach ($aliases as $id => $alias) {
            if ($id && '.' !== $id[0] && (!$alias->isPublic() || $alias->isPrivate())) {
                while (isset($aliases[$target = (string) $alias])) {
                    $alias = $aliases[$target];
                }
                if (isset($definitions[$target]) && !$definitions[$target]->getErrors() && !$definitions[$target]->isAbstract()) {
                    $privateServices[$id] = new Reference($target, ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE);
                }
            }
        }

        if ($privateServices) {
            $id = (string) ServiceLocatorTagPass::register($container, $privateServices);
            $container->setDefinition('test.private_services_locator', $container->getDefinition($id))->setPublic(true);
            $container->removeDefinition($id);
        }
    }
}
