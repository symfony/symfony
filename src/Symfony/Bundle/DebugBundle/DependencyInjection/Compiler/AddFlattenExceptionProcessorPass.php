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
 * Registers the exception processors.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class AddFlattenExceptionProcessorPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('exception_flattener')) {
            return;
        }

        $processors = array();
        foreach ($container->findTaggedServiceIds('exception.processor') as $id => $tags) {
            $priority = isset($tags[0]['priority']) ? $tags[0]['priority'] : 0;
            $processors[$priority][] = new Reference($id);
        }

        if (empty($processors)) {
            return;
        }

        // sort by priority and flatten
        krsort($processors);
        $processors = call_user_func_array('array_merge', $processors);

        $container->getDefinition('exception_flattener')->replaceArgument(0, $processors);
    }
}
