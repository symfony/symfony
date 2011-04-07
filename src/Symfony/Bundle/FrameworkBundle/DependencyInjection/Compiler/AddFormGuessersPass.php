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
 * Adds all services with the tag "form.guesser" as constructor argument of the
 * "form.factory" service
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class AddFormGuessersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.factory')) {
            return;
        }

        $guessers = array();

        foreach ($container->findTaggedServiceIds('form.guesser') as $serviceId => $tag) {
            $guessers[] = new Reference($serviceId);
        }

        $container->getDefinition('form.factory')->setArgument(1, $guessers);
    }
}
