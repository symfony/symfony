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

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class RemoveUnusedSessionMarshallingHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('session.marshalling_handler')) {
            return;
        }

        $isMarshallerDecorated = false;

        foreach ($container->getDefinitions() as $definition) {
            $decorated = $definition->getDecoratedService();
            if (null !== $decorated && 'session.marshaller' === $decorated[0]) {
                $isMarshallerDecorated = true;

                break;
            }
        }

        if (!$isMarshallerDecorated) {
            $container->removeDefinition('session.marshalling_handler');
        }
    }
}
