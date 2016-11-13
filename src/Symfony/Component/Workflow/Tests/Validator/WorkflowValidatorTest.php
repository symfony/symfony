<?php

namespace Symfony\Component\Workflow\Tests\Validator;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Tests\WorkflowTest;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\WorkflowValidator;

class WorkflowValidatorTest extends WorkflowTest
{
    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage The marking store of workflow "foo" can not store many places.
     */
    public function testSinglePlaceWorkflowValidatorAndComplexWorkflow()
    {
        $definition = $this->createComplexWorkflow();

        (new WorkflowValidator(true))->validate($definition, 'foo');
    }

    public function testSinglePlaceWorkflowValidatorAndSimpleWorkflow()
    {
        $places = array('a', 'b');
        $transition = new Transition('t1', 'a', 'b');
        $definition = new Definition($places, array($transition));

        (new WorkflowValidator(true))->validate($definition, 'foo');
    }
}
