<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Remove private aliases from the container. They were only used to establish
 * dependencies between services, and these dependencies have been resolved in
 * one of the previous passes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class RemovePrivateAliasesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getAliases() as $id => $alias) {
            if ($alias->isPublic()) {
                continue;
            }

            $container->removeAlias($id);
        }
    }
}