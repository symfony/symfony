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
        if (!$container->hasDefinition('test.private_services_locator')) {
            return;
        }

        $privateContainer = $container->getDefinition('test.private_services_locator');
        $definitions = $container->getDefinitions();
        $privateServices = $privateContainer->getArgument(0);

        foreach ($privateServices as $id => $argument) {
            if (isset($definitions[$target = (string) $argument->getValues()[0]])) {
                $argument->setValues(array(new Reference($target)));
            } else {
                unset($privateServices[$id]);
            }
        }

        $privateContainer->replaceArgument(0, $privateServices);
    }
}
