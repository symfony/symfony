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

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class AddConstraintValidatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('validator.validator_factory')) {
            return;
        }

        $validators = array();
        foreach ($container->findTaggedServiceIds('validator.constraint_validator') as $id => $attributes) {
            $definition = $container->getDefinition($id);

            if ($definition->isAbstract()) {
                continue;
            }

            if (isset($attributes[0]['alias'])) {
                $validators[$attributes[0]['alias']] = new Reference($id);
            }

            $validators[$definition->getClass()] = new Reference($id);
        }

        $container->getDefinition('validator.validator_factory')->replaceArgument(0, new ServiceLocatorArgument($validators));
    }
}
