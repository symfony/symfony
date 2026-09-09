<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the services that depend on the default lock factory, which only LockBundle can register.
 *
 * @internal
 */
class DefaultLockFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('.rate_limiter.lock_factories')) {
            $hasDefaultFactory = $container->hasAlias('lock.factory');
            $lockFactories = $container->getParameter('.rate_limiter.lock_factories');
            $container->getParameterBag()->remove('.rate_limiter.lock_factories');

            foreach ($lockFactories as $id => [$index, $requiredBy]) {
                if ($hasDefaultFactory) {
                    $container->getDefinition($id)->replaceArgument($index, new Reference('lock.factory'));
                } elseif (null !== $requiredBy) {
                    throw new LogicException(\sprintf('%s requires the Lock component to be configured.', $requiredBy));
                }
            }
        }
    }
}
