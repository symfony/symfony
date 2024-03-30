<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class VirtualRequestStackPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('.virtual_request_stack')) {
            return;
        }

        if ($container->hasDefinition('debug.event_dispatcher')) {
            $container->getDefinition('debug.event_dispatcher')->replaceArgument(3, new Reference('request_stack', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        }

        if ($container->hasDefinition('debug.log_processor')) {
            $container->getDefinition('debug.log_processor')->replaceArgument(0, new Reference('request_stack'));
        }
    }
}
