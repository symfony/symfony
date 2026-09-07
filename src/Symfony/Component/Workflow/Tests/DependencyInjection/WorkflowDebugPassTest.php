<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Workflow\Debug\TraceableWorkflow;
use Symfony\Component\Workflow\DependencyInjection\WorkflowDebugPass;
use Symfony\Component\Workflow\Workflow;

class WorkflowDebugPassTest extends TestCase
{
    public function testWorkflowsAreDecoratedWhenTheStopwatchIsAvailable()
    {
        $container = new ContainerBuilder();
        $container->register('debug.stopwatch', Stopwatch::class);
        $container->register('workflow.article', Workflow::class)->addTag('workflow', ['name' => 'article']);

        (new WorkflowDebugPass())->process($container);

        $this->assertTrue($container->hasDefinition('debug.workflow.article'));
        $definition = $container->getDefinition('debug.workflow.article');
        $this->assertSame(TraceableWorkflow::class, $definition->getClass());
        $this->assertSame('workflow.article', $definition->getDecoratedService()[0]);
    }

    public function testWorkflowsAreNotDecoratedWithoutTheStopwatch()
    {
        $container = new ContainerBuilder();
        $container->register('workflow.article', Workflow::class)->addTag('workflow', ['name' => 'article']);

        (new WorkflowDebugPass())->process($container);

        $this->assertFalse($container->hasDefinition('debug.workflow.article'));
    }
}
