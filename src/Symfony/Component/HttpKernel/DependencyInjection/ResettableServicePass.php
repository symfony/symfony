<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\EventListener\ServiceResetListener;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
class ResettableServicePass implements CompilerPassInterface
{
    private $tagName;

    /**
     * @param string $tagName
     */
    public function __construct($tagName = 'kernel.reset')
    {
        $this->tagName = $tagName;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(ServiceResetListener::class)) {
            return;
        }

        $services = $methods = array();

        foreach ($container->findTaggedServiceIds($this->tagName, true) as $id => $tags) {
            $services[$id] = new Reference($id, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE);
            $attributes = $tags[0];

            if (!isset($attributes['method'])) {
                throw new RuntimeException(sprintf('Tag %s requires the "method" attribute to be set.', $this->tagName));
            }

            $methods[$id] = $attributes['method'];
        }

        if (empty($services)) {
            $container->removeDefinition(ServiceResetListener::class);

            return;
        }

        $container->findDefinition(ServiceResetListener::class)
            ->replaceArgument(0, new IteratorArgument($services))
            ->replaceArgument(1, $methods);
    }
}
