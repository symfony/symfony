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
    protected bool $skipScalars = true;

    public function process(ContainerBuilder $container): void
    {
        parent::process($container);

        foreach ($container->getAliases() as $id => $alias) {
            $aliasId = (string) $alias;
            $this->currentId = $id;

            if ($aliasId !== $defId = $this->getDefinitionId($aliasId, $container)) {
                $newAlias = $container->setAlias($id, $defId)->setPublic($alias->isPublic());

                if ($alias->isDeprecated()) {
                    $newAlias->setDeprecated(...array_values($alias->getDeprecation('%alias_id%')));
                }
            }
        }
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
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
            $referencingDefinition = $container->hasDefinition($this->currentId) ? $container->getDefinition($this->currentId) : $container->getAlias($this->currentId);
            if (!$referencingDefinition->isDeprecated()) {
                $deprecation = $alias->getDeprecation($id);
                trigger_deprecation($deprecation['package'], $deprecation['version'], rtrim($deprecation['message'], '. ').'. It is being referenced by the "%s" '.($container->hasDefinition($this->currentId) ? 'service.' : 'alias.'), $this->currentId);
            }
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
