<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds all services with the tag "form.config" as argument
 * to the "form.config.loader" service
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class AddFormConfigsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.config.loader')) {
            return;
        }

        // Builds an array with service IDs as keys and tag aliases as values
        $configs = array_map(function ($arguments) {
            if (!isset($arguments[0]['alias'])) {
                // TODO throw exception
            }

            return $arguments[0]['alias'];
        }, $container->findTaggedServiceIds('form.config'));

        // Flip, because we want tag aliases (= config identifiers) as keys
        $configs = array_flip($configs);

        $container->getDefinition('form.config.loader')->setArgument(1, $configs);
    }
}