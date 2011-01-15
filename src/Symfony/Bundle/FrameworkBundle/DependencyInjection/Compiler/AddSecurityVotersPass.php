<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds all configured security voters to the access decision manager
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AddSecurityVotersPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.access.decision_manager')) {
            return;
        }

        $voters = array_map(function($id) {
            return new Reference($id);
        }, array_keys($container->findTaggedServiceIds('security.voter')));

        $definition = $container->getDefinition('security.access.decision_manager');
        $arguments = $definition->getArguments();
        $arguments[0] = $voters;
        $definition->setArguments($arguments);
    }
}