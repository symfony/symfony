<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * Replaces all references to aliases with references to the actual service.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveReferencesToAliasesPass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        parent::process($container);

        foreach ($container->getAliases() as $id => $alias) {
            $aliasId = (string) $alias;
            if ($aliasId !== $defId = $this->getDefinitionId($aliasId, $container)) {
                $container->setAlias($id, $defId)->setPublic($alias->isPublic())->setPrivate($alias->isPrivate());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if ($value instanceof Reference) {
            $defId = $this->getDefinitionId($id = (string) $value, $this->container);

            if ($defId !== $id) {
                return new Reference($defId, $value->getInvalidBehavior());
            }
        }

        return parent::processValue($value);
    }

    private function getDefinitionId(string $id, ContainerBuilder $container): string
    {
        $seen = array();
        while ($container->hasAlias($id)) {
            if (isset($seen[$id])) {
                throw new ServiceCircularReferenceException($id, array_keys($seen));
            }
            $seen[$id] = true;
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }
}
