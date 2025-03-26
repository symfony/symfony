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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\LazyServiceDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LazyServiceInstantiator implements InstantiatorInterface
{
    public function instantiateProxy(ContainerInterface $container, Definition $definition, string $id, callable $realInstantiator): object
    {
        $dumper = new LazyServiceDumper();

        if (!$dumper->isProxyCandidate($definition, $asGhostObject, $id)) {
            throw new InvalidArgumentException(\sprintf('Cannot instantiate lazy proxy for service "%s".', $id));
        }

        if (\PHP_VERSION_ID >= 80400 && $asGhostObject) {
            return (new \ReflectionClass($definition->getClass()))->newLazyGhost(static function ($ghost) use ($realInstantiator) { $realInstantiator($ghost); });
        }

        $class = null;
        if (!class_exists($proxyClass = $dumper->getProxyClass($definition, $asGhostObject, $class), false)) {
            eval($dumper->getProxyCode($definition, $id));
        }

        if ($definition->getClass() === $proxyClass) {
            return $class->newLazyProxy($realInstantiator);
        }

        return \PHP_VERSION_ID < 80400 && $asGhostObject ? $proxyClass::createLazyGhost($realInstantiator) : $proxyClass::createLazyProxy($realInstantiator);
    }
}
