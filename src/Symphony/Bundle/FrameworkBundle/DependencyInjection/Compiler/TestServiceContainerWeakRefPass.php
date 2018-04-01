<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TestServiceContainerWeakRefPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('test.service_container')) {
            return;
        }

        $privateServices = array();
        $definitions = $container->getDefinitions();

        foreach ($definitions as $id => $definition) {
            if (!$definition->isPublic() && !$definition->getErrors() && !$definition->isAbstract()) {
                $privateServices[$id] = new ServiceClosureArgument(new Reference($id, ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE));
            }
        }

        $aliases = $container->getAliases();

        foreach ($aliases as $id => $alias) {
            if (!$alias->isPublic()) {
                while (isset($aliases[$target = (string) $alias])) {
                    $alias = $aliases[$target];
                }
                if (isset($definitions[$target]) && !$definitions[$target]->getErrors() && !$definitions[$target]->isAbstract()) {
                    $privateServices[$id] = new ServiceClosureArgument(new Reference($target, ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE));
                }
            }
        }

        if ($privateServices) {
            $definitions[(string) $definitions['test.service_container']->getArgument(2)]->replaceArgument(0, $privateServices);
        }
    }
}
