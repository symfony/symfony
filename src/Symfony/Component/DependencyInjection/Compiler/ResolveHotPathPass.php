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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Propagate "container.hot_path" tags to referenced services.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveHotPathPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    private array $resolvedIds = [];

    public function process(ContainerBuilder $container): void
    {
        try {
            parent::process($container);
            $container->getDefinition('service_container')->clearTag('container.hot_path');
        } finally {
            $this->resolvedIds = [];
        }
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if ($value instanceof ArgumentInterface) {
            return $value;
        }

        if ($value instanceof Definition && $isRoot) {
            if ($value->isDeprecated()) {
                return $value->clearTag('container.hot_path');
            }

            $this->resolvedIds[$this->currentId] = true;

            if (!$value->hasTag('container.hot_path')) {
                return $value;
            }
        }

        if ($value instanceof Reference && ContainerBuilder::IGNORE_ON_UNINITIALIZED_REFERENCE !== $value->getInvalidBehavior() && $this->container->hasDefinition($id = (string) $value)) {
            $definition = $this->container->getDefinition($id);

            if ($definition->isDeprecated() || $definition->hasTag('container.hot_path')) {
                return $value;
            }

            $definition->addTag('container.hot_path');

            if (isset($this->resolvedIds[$id])) {
                parent::processValue($definition, false);
            }

            return $value;
        }

        return parent::processValue($value, $isRoot);
    }
}
