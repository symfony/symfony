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
use Symfony\Component\DependencyInjection\Alias;

/**
 * Overwrites a service but keeps the overridden one.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Diego Saint Esteben <diego@saintesteben.me>
 */
class DecoratorServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definitions = new \SplPriorityQueue();
        $order = PHP_INT_MAX;

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$decorated = $definition->getDecoratedService()) {
                continue;
            }
            $definitions->insert(array($id, $definition), array($decorated[2], --$order));
        }

        foreach ($definitions as list($id, $definition)) {
            list($inner, $renamedId) = $definition->getDecoratedService();

            $definition->setDecoratedService(null);

            if (!$renamedId) {
                $renamedId = $id.'.inner';
            }

            // we create a new alias/service for the service we are replacing
            // to be able to reference it in the new one
            if ($container->hasAlias($inner)) {
                $alias = $container->getAlias($inner);
                $public = $alias->isPublic();
                $private = $alias->isPrivate();
                $container->setAlias($renamedId, new Alias((string) $alias, false));
            } else {
                $decoratedDefinition = $container->getDefinition($inner);
                $definition->setTags(array_merge($decoratedDefinition->getTags(), $definition->getTags()));
                if ($types = array_merge($decoratedDefinition->getAutowiringTypes(false), $definition->getAutowiringTypes(false))) {
                    $definition->setAutowiringTypes($types);
                }
                $public = $decoratedDefinition->isPublic();
                $private = $decoratedDefinition->isPrivate();
                $decoratedDefinition->setPublic(false);
                $decoratedDefinition->setTags(array());
                if ($decoratedDefinition->getAutowiringTypes(false)) {
                    $decoratedDefinition->setAutowiringTypes(array());
                }
                $container->setDefinition($renamedId, $decoratedDefinition);
            }

            $container->setAlias($inner, $id)->setPublic($public)->setPrivate($private);
        }
    }
}
