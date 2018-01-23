<?php

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass;
use Symfony\Component\Workflow\Transition;

class ValidateWorkflowsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = $this->getMockBuilder(ContainerBuilder::class)->getMock();
        $container
            ->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('workflow.definition')
            ->willReturn(array('definition1' => array('workflow.definition' => array('name' => 'wf1', 'type' => 'state_machine', 'marking_store' => 'foo'))));

        $container
            ->expects($this->once())
            ->method('get')
            ->with('definition1')
            ->willReturn(new Definition(array('a', 'b', 'c'), array(new Transition('t1', 'a', 'b'), new Transition('t2', 'a', 'c'))));

        (new ValidateWorkflowsPass())->process($container);
    }
}
