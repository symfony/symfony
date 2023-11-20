<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Debug\TraceableWorkflow;

/**
 * Adds all configured security voters to the access decision manager.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class WorkflowDebugPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('workflow') as $id => $attributes) {
            $container->register("debug.{$id}", TraceableWorkflow::class)
                ->setDecoratedService($id)
                ->setArguments([
                    new Reference("debug.{$id}.inner"),
                    new Reference('debug.stopwatch'),
                ]);
        }
    }
}
