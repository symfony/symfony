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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Applies the "container.service_locator" tag by wrapping references into ServiceClosureArgument instances.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ServiceLocatorTagPass extends AbstractRecursivePass
{
    use PriorityTaggedServiceTrait;

    protected function processValue($value, bool $isRoot = false)
    {
        if ($value instanceof ServiceLocatorArgument) {
            if ($value->getTaggedIteratorArgument()) {
                $value->setValues($this->findAndSortTaggedServices($value->getTaggedIteratorArgument(), $this->container));
            }

            return self::register($this->container, $value->getValues());
        }

        if (!$value instanceof Definition || !$value->hasTag('container.service_locator')) {
            return parent::processValue($value, $isRoot);
        }

        if (!$value->getClass()) {
            $value->setClass(ServiceLocator::class);
        }

        $arguments = $value->getArguments();
        if (!isset($arguments[0]) || !\is_array($arguments[0])) {
            throw new InvalidArgumentException(sprintf('Invalid definition for service "%s": an array of references is expected as first argument when the "container.service_locator" tag is set.', $this->currentId));
        }

        $i = 0;

        foreach ($arguments[0] as $k => $v) {
            if ($v instanceof ServiceClosureArgument) {
                continue;
            }
            if (!$v instanceof Reference) {
                throw new InvalidArgumentException(sprintf('Invalid definition for service "%s": an array of references is expected as first argument when the "container.service_locator" tag is set, "%s" found for key "%s".', $this->currentId, \is_object($v) ? \get_class($v) : \gettype($v), $k));
            }

            if ($i === $k) {
                unset($arguments[0][$k]);

                $k = (string) $v;
                ++$i;
            } elseif (\is_int($k)) {
                $i = null;
            }
            $arguments[0][$k] = new ServiceClosureArgument($v);
        }
        ksort($arguments[0]);

        $value->setArguments($arguments);

        $id = '.service_locator.'.ContainerBuilder::hash($value);

        if ($isRoot) {
            if ($id !== $this->currentId) {
                $this->container->setAlias($id, new Alias($this->currentId, false));
            }

            return $value;
        }

        $this->container->setDefinition($id, $value->setPublic(false));

        return new Reference($id);
    }

    /**
     * @param Reference[] $refMap
     */
    public static function register(ContainerBuilder $container, array $refMap, string $callerId = null): Reference
    {
        foreach ($refMap as $id => $ref) {
            if (!$ref instanceof Reference) {
                throw new InvalidArgumentException(sprintf('Invalid service locator definition: only services can be referenced, "%s" found for key "%s". Inject parameter values using constructors instead.', \is_object($ref) ? \get_class($ref) : \gettype($ref), $id));
            }
            $refMap[$id] = new ServiceClosureArgument($ref);
        }
        ksort($refMap);

        $locator = (new Definition(ServiceLocator::class))
            ->addArgument($refMap)
            ->setPublic(false)
            ->addTag('container.service_locator');

        if (null !== $callerId && $container->hasDefinition($callerId)) {
            $locator->setBindings($container->getDefinition($callerId)->getBindings());
        }

        if (!$container->hasDefinition($id = '.service_locator.'.ContainerBuilder::hash($locator))) {
            $container->setDefinition($id, $locator);
        }

        if (null !== $callerId) {
            $locatorId = $id;
            // Locators are shared when they hold the exact same list of factories;
            // to have them specialized per consumer service, we use a cloning factory
            // to derivate customized instances from the prototype one.
            $container->register($id .= '.'.$callerId, ServiceLocator::class)
                ->setPublic(false)
                ->setFactory([new Reference($locatorId), 'withContext'])
                ->addTag('container.service_locator_context', ['id' => $callerId])
                ->addArgument($callerId)
                ->addArgument(new Reference('service_container'));
        }

        return new Reference($id);
    }
}
