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
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Reference;

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
            $this->currentId = $id;

            if ($aliasId !== $defId = $this->getDefinitionId($aliasId, $container)) {
                $container->setAlias($id, $defId)->setPublic($alias->isPublic());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, bool $isRoot = false)
    {
        if (!$value instanceof Reference) {
            return parent::processValue($value, $isRoot);
        }

        $defId = $this->getDefinitionId($id = (string) $value, $this->container);

        return $defId !== $id ? new Reference($defId, $value->getInvalidBehavior()) : $value;
    }

    private function getDefinitionId(string $id, ContainerBuilder $container): string
    {
        if (!$container->hasAlias($id)) {
            return $id;
        }

        $alias = $container->getAlias($id);

        if ($alias->isDeprecated()) {
            $deprecation = $alias->getDeprecation($id);
            trigger_deprecation($deprecation['package'], $deprecation['version'], rtrim($deprecation['message'], '. ').'. It is being referenced by the "%s" '.($container->hasDefinition($this->currentId) ? 'service.' : 'alias.'), $this->currentId);
        }

        $seen = [];
        do {
            if (isset($seen[$id])) {
                throw new ServiceCircularReferenceException($id, array_merge(array_keys($seen), [$id]));
            }

            $seen[$id] = true;
            $id = (string) $container->getAlias($id);
        } while ($container->hasAlias($id));

        return $id;
    }
}
