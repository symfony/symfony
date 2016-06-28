<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Security\Core\Authorization\DebugAccessDecisionManager;

/**
 * Adds all configured security voters to the access decision manager.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class AddDebugAccessDecisionManagerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('profiler')) {
            return;
        }

        $definition = new Definition(DebugAccessDecisionManager::class, array(new Reference('debug.security.access.decision_manager.inner')));
        $definition->setPublic(false);
        $definition->setDecoratedService('security.access.decision_manager');

        $container->setDefinition('debug.security.access.decision_manager', $definition);
    }
}
