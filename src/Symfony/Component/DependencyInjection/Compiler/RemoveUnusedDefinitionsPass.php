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
use Symfony\Component\DependencyInjection\Reference;

/**
 * Removes unused service definitions from the container.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RemoveUnusedDefinitionsPass extends AbstractRecursivePass
{
    private array $connectedIds = [];

    /**
     * Processes the ContainerBuilder to remove unused definitions.
     *
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        try {
            $this->enableExpressionProcessing();
            $this->container = $container;
            $connectedIds = [];
            $aliases = $container->getAliases();

            foreach ($aliases as $id => $alias) {
                if ($alias->isPublic()) {
                    $this->connectedIds[] = (string) $aliases[$id];
                }
            }

            foreach ($container->getDefinitions() as $id => $definition) {
                if ($definition->isPublic()) {
                    $connectedIds[$id] = true;
                    $this->processValue($definition);
                }
            }

            while ($this->connectedIds) {
                $ids = $this->connectedIds;
                $this->connectedIds = [];
                foreach ($ids as $id) {
                    if (!isset($connectedIds[$id]) && $container->hasDefinition($id)) {
                        $connectedIds[$id] = true;
                        $this->processValue($container->getDefinition($id));
                    }
                }
            }

            foreach ($container->getDefinitions() as $id => $definition) {
                if (!isset($connectedIds[$id])) {
                    $container->removeDefinition($id);
                    $container->resolveEnvPlaceholders(!$definition->hasErrors() ? serialize($definition) : $definition);
                    $container->log($this, sprintf('Removed service "%s"; reason: unused.', $id));
                }
            }
        } finally {
            $this->container = null;
            $this->connectedIds = [];
        }
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if (!$value instanceof Reference) {
            return parent::processValue($value, $isRoot);
        }

        if (ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE !== $value->getInvalidBehavior()) {
            $this->connectedIds[] = (string) $value;
        }

        return $value;
    }
}
