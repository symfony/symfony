<?php

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Workflow\Definition as WorkflowDefinition;
use Symfony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass;
use Symfony\Component\Workflow\Transition;

class ValidateWorkflowsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('definition1', WorkflowDefinition::class)
            ->addArgument(['a', 'b', 'c'])
            ->addArgument([
                new Definition(Transition::class, ['t1', 'a', 'b']),
                new Definition(Transition::class, ['t2', 'a', 'c']),
            ])
            ->addTag('workflow.definition', ['name' => 'wf1', 'type' => 'state_machine', 'marking_store' => 'foo']);

        (new ValidateWorkflowsPass())->process($container);

        $workflowDefinition = $container->get('definition1');

        $this->assertSame(['a' => 'a', 'b' => 'b', 'c' => 'c'], $workflowDefinition->getPlaces());
        $this->assertEquals([new Transition('t1', 'a', 'b'), new Transition('t2', 'a', 'c')], $workflowDefinition->getTransitions());
    }
}
