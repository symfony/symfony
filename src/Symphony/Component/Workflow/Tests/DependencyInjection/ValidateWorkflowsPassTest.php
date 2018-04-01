<?php

namespace Symphony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\Workflow\Definition as WorkflowDefinition;
use Symphony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass;
use Symphony\Component\Workflow\Transition;

class ValidateWorkflowsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('definition1', WorkflowDefinition::class)
            ->addArgument(array('a', 'b', 'c'))
            ->addArgument(array(
                new Definition(Transition::class, array('t1', 'a', 'b')),
                new Definition(Transition::class, array('t2', 'a', 'c')),
            ))
            ->addTag('workflow.definition', array('name' => 'wf1', 'type' => 'state_machine', 'marking_store' => 'foo'));

        (new ValidateWorkflowsPass())->process($container);

        $workflowDefinition = $container->get('definition1');

        $this->assertSame(array('a' => 'a', 'b' => 'b', 'c' => 'c'), $workflowDefinition->getPlaces());
        $this->assertEquals(array(new Transition('t1', 'a', 'b'), new Transition('t2', 'a', 'c')), $workflowDefinition->getTransitions());
    }
}
