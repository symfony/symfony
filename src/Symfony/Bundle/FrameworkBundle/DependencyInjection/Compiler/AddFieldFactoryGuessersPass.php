<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds all services with the tag "form.field_factory_guesser" as argument
 * to the "form.field_factory" service
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class AddFieldFactoryGuessersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.field_factory')) {
            return;
        }

        $guessers = array_map(function($id) {
            return new Reference($id);
        }, array_keys($container->findTaggedServiceIds('form.field_factory.guesser')));

        $container->getDefinition('form.field_factory')->setArgument(0, $guessers);
    }
}