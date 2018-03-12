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
