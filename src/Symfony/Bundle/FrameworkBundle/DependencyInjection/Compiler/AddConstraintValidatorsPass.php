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

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

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
                $validators[$attributes[0]['alias']] = new ServiceClosureArgument(new Reference($id));
            }

            $validators[$definition->getClass()] = new ServiceClosureArgument(new Reference($id));
        }

        $container->getDefinition('validator.validator_factory')->replaceArgument(0, (new Definition(ServiceLocator::class, array($validators)))->addTag('container.service_locator'));
    }
}
