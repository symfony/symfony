<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TestServiceContainerRealRefPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('test.service_container')) {
            return;
        }

        $testContainer = $container->getDefinition('test.service_container');
        $privateContainer = $container->getDefinition((string) $testContainer->getArgument(2));
        $definitions = $container->getDefinitions();

        foreach ($privateContainer->getArgument(0) as $id => $argument) {
            if (isset($definitions[$target = (string) $argument->getValues()[0]])) {
                $argument->setValues(array(new Reference($target)));
            }
        }
    }
}
