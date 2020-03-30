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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal to be removed in 6.0
 */
class SessionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('session')) {
            return;
        }

        $bags = [
            'session.flash_bag' => $container->hasDefinition('session.flash_bag') ? $container->getDefinition('session.flash_bag') : null,
            'session.attribute_bag' => $container->hasDefinition('session.attribute_bag') ? $container->getDefinition('session.attribute_bag') : null,
        ];

        foreach ($container->getDefinition('session')->getArguments() as $v) {
            if (!$v instanceof Reference || !isset($bags[$bag = (string) $v]) || !\is_array($factory = $bags[$bag]->getFactory())) {
                continue;
            }

            if ([0, 1] !== array_keys($factory) || !$factory[0] instanceof Reference || 'session' !== (string) $factory[0]) {
                continue;
            }

            if ('get'.ucfirst(substr($bag, 8, -4)).'Bag' !== $factory[1]) {
                continue;
            }

            $bags[$bag]->setFactory(null);
        }
    }
}
