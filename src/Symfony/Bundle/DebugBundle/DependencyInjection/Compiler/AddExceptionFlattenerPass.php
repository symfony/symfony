<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the exception flatteners.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class AddExceptionFlattenerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('exception_processor')) {
            return;
        }

        $flatteners = array();
        foreach ($container->findTaggedServiceIds('exception.flattener') as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $flatteners[$priority][] = new Reference($id);
        }

        if (empty($flatteners)) {
            return;
        }

        // sort by priority and flatten
        krsort($flatteners);
        $flatteners = call_user_func_array('array_merge', $flatteners);

        $container->getDefinition('exception_processor')->replaceArgument(0, $flatteners);
    }
}
