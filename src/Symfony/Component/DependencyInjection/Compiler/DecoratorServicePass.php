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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Overwrites a service but keeps the overridden one.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Diego Saint Esteben <diego@saintesteben.me>
 */
class DecoratorServicePass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    /**
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        $definitions = new \SplPriorityQueue();
        $order = \PHP_INT_MAX;

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$decorated = $definition->getDecoratedService()) {
                continue;
            }
            $definitions->insert([$id, $definition], [$decorated[2], --$order]);
        }
        $decoratingDefinitions = [];
        $decoratedIds = [];

        $tagsToKeep = $container->hasParameter('container.behavior_describing_tags')
            ? $container->getParameter('container.behavior_describing_tags')
            : ['proxy', 'container.do_not_inline', 'container.service_locator', 'container.service_subscriber', 'container.service_subscriber.locator'];

        foreach (self::delayDecoratorsOfDecorators($container, iterator_to_array($definitions, false)) as [$id, $definition]) {
            $decoratedService = $definition->getDecoratedService();
            [$inner, $renamedId] = $decoratedService;
            $invalidBehavior = $decoratedService[3] ?? ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;

            $definition->setDecoratedService(null);

            if (!$renamedId) {
                $renamedId = $id.'.inner';
            }

            $decoratedIds[$inner] ??= $renamedId;
            $this->currentId = $renamedId;
            $this->processValue($definition);

            $definition->innerServiceId = $renamedId;
            $definition->decorationOnInvalid = $invalidBehavior;

            // we create a new alias/service for the service we are replacing
            // to be able to reference it in the new one
            if ($container->hasAlias($inner)) {
                $alias = $container->getAlias($inner);
                $public = $alias->isPublic();
                $container->setAlias($renamedId, new Alias((string) $alias, false));
                $decoratedDefinition = $container->findDefinition($alias);
            } elseif ($container->hasDefinition($inner)) {
                $decoratedDefinition = $container->getDefinition($inner);
                $public = $decoratedDefinition->isPublic();
                $decoratedDefinition->setPublic(false);
                $container->setDefinition($renamedId, $decoratedDefinition);
                $decoratingDefinitions[$inner] = $decoratedDefinition;
            } elseif (ContainerInterface::IGNORE_ON_INVALID_REFERENCE === $invalidBehavior) {
                $container->removeDefinition($id);
                continue;
            } elseif (ContainerInterface::NULL_ON_INVALID_REFERENCE === $invalidBehavior) {
                $public = $definition->isPublic();
                $decoratedDefinition = null;
            } else {
                throw new ServiceNotFoundException($inner, $id);
            }

            if ($decoratedDefinition?->isSynthetic()) {
                throw new InvalidArgumentException(\sprintf('A synthetic service cannot be decorated: service "%s" cannot decorate "%s".', $id, $inner));
            }

            if (isset($decoratingDefinitions[$inner])) {
                $decoratingDefinition = $decoratingDefinitions[$inner];

                $decoratingTags = $decoratingDefinition->getTags();
                $resetTags = [];

                // Behavior-describing tags must not be transferred out to decorators
                foreach ($tagsToKeep as $containerTag) {
                    if (isset($decoratingTags[$containerTag])) {
                        $resetTags[$containerTag] = $decoratingTags[$containerTag];
                        unset($decoratingTags[$containerTag]);
                    }
                }

                $definition->setTags(array_merge($decoratingTags, $definition->getTags()));
                $decoratingDefinition->setTags($resetTags);
                $decoratingDefinitions[$inner] = $definition;
            }

            $container->setAlias($inner, $id)->setPublic($public);
        }

        foreach ($decoratingDefinitions as $inner => $definition) {
            $definition->addTag('container.decorator', ['id' => $inner, 'inner' => $decoratedIds[$inner]]);
        }
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if ($value instanceof Reference && '.inner' === (string) $value) {
            return new Reference($this->currentId, $value->getInvalidBehavior());
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * Delays the decorators of a decorator until all the decorators of the service it decorates are processed.
     *
     * Tags move from a decorated service to its outermost decorator one decorator at a time, and decorating a decorator too early would strand them on it.
     *
     * @param list<array{string, Definition}> $definitions
     *
     * @return list<array{string, Definition}>
     */
    private static function delayDecoratorsOfDecorators(ContainerBuilder $container, array $definitions): array
    {
        $decoratedIds = $remaining = [];

        foreach ($definitions as [$id, $definition]) {
            $inner = $decoratedIds[$id] = $definition->getDecoratedService()[0];
            $remaining[$inner] = ($remaining[$inner] ?? 0) + 1;
        }

        $sorted = $delayed = [];

        foreach ($definitions as $entry) {
            $delayed[] = $entry;

            do {
                $count = \count($sorted);

                foreach ($delayed as $k => [$id]) {
                    $inner = $decoratedIds[$id];

                    // a missing service has no tags to move, and delaying would change which decorators end up ignored
                    if (isset($decoratedIds[$inner]) && $remaining[$decoratedIds[$inner]] && $container->has($decoratedIds[$inner])) {
                        continue;
                    }

                    $sorted[] = $delayed[$k];
                    unset($delayed[$k]);
                    --$remaining[$inner];
                }
            } while ($delayed && $count < \count($sorted));
        }

        return array_merge($sorted, $delayed);
    }
}
