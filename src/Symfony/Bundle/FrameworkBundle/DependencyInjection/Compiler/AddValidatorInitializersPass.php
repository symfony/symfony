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

class AddValidatorInitializersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('validator.builder')) {
            return;
        }

        $validatorBuilder = $container->getDefinition('validator.builder');

        $initializers = array();
        foreach ($container->findTaggedServiceIds('validator.initializer') as $id => $attributes) {
            $initializers[] = new Reference($id);
        }

        $validatorBuilder->addMethodCall('addObjectInitializers', array($initializers));
    }
}
