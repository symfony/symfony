<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\LazyProxy\Instantiator;

use Symfony\Bridge\ProxyManager\LazyProxy\Instantiator\RuntimeInstantiator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\LazyServiceDumper;
use Symfony\Component\VarExporter\LazyGhostObjectInterface;
use Symfony\Component\VarExporter\LazyGhostObjectTrait;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LazyServiceInstantiator implements InstantiatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, string $id, callable $realInstantiator): object
    {
        $dumper = new LazyServiceDumper();

        if ($dumper->useProxyManager($definition)) {
            return (new RuntimeInstantiator())->instantiateProxy($container, $definition, $id, $realInstantiator);
        }

        if (!class_exists($proxyClass = $dumper->getProxyClass($definition), false)) {
            eval(sprintf('class %s extends %s implements %s { use %s; }', $proxyClass, $definition->getClass(), LazyGhostObjectInterface::class, LazyGhostObjectTrait::class));
        }

        return $proxyClass::createLazyGhostObject($realInstantiator);
    }
}
