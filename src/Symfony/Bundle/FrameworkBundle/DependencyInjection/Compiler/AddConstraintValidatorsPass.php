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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class AddConstraintValidatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('validator.validator_factory')) {
            return;
        }

        $validators = array();
        foreach ($container->findTaggedServiceIds('validator.constraint_validator') as $id => $attributes) {
            if (isset($attributes[0]['alias'])) {
                $validators[$attributes[0]['alias']] = $id;
            }

            $definition = $container->getDefinition($id);

            if (!$definition->isPublic()) {
                throw new InvalidArgumentException(sprintf('The service "%s" must be public as it can be lazy-loaded.', $id));
            }

            if ($definition->isAbstract()) {
                throw new InvalidArgumentException(sprintf('The service "%s" must not be abstract as it can be lazy-loaded.', $id));
            }

            $validators[$definition->getClass()] = $id;
        }

        $container->getDefinition('validator.validator_factory')->replaceArgument(1, $validators);
    }
}
