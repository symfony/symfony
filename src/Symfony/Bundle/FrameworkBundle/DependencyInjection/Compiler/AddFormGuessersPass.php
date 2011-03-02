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
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds all services with the tag "form.guesser" as calls to the "addGuesser"
 * method of the "form.factory" service
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class AddFormGuessersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.factory')) {
            return;
        }

        // Builds an array with service IDs as keys and tag aliases as values
        $guessers = array_map(function ($id) {
            return new Reference($id);
        }, array_keys($container->findTaggedServiceIds('form.guesser')));

        $service = $container->getDefinition('form.factory');

        foreach ($guessers as $guesser) {
            $service->addMethodCall('addGuesser', array($guesser));
        }
    }
}