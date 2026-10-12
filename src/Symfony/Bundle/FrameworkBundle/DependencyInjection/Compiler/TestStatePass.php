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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the test container the services tagged "kernel.test_state".
 *
 * With "method", the method is called when KernelTestCase shuts the kernel down at the end of a test.
 * With "keep", a copy of the service is kept before each reset of the kernel, until the end of the test.
 * The copy is shallow: the reset() method of the service must replace its state instead of changing it in place, unless the service implements __clone().
 *
 * @internal
 */
final class TestStatePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('test.service_container')) {
            return;
        }

        $keptServices = $methods = [];

        foreach ($container->findTaggedServiceIds('kernel.test_state', true) as $id => $tags) {
            foreach ($tags as $attributes) {
                if (!($attributes['keep'] ?? false) && !isset($attributes['method'])) {
                    throw new InvalidArgumentException(\sprintf('Tag "kernel.test_state" requires the "keep" or the "method" attribute to be set on service "%s".', $id));
                }

                if ($attributes['keep'] ?? false) {
                    $keptServices[$id] = new Reference($id, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE);
                }

                if (isset($attributes['method'])) {
                    $methods[$id][] = $attributes['method'];
                }
            }
        }

        $container->getDefinition('test.service_container')
            ->setArgument(4, new IteratorArgument($keptServices))
            ->setArgument(5, $methods);
    }
}
