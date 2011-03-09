<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\ZendBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds tagged zend.logger.writer services to zend.logger service
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ZendLoggerWriterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('zend.logger')) {
            return;
        }

        $definition = $container->getDefinition('zend.logger');

        foreach ($container->findTaggedServiceIds('zend.logger.writer') as $id => $attributes) {
            $definition->addMethodCall('addWriter', array(new Reference($id)));
        }
    }
}
