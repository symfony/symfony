<?php

namespace Symfony\Component\Workflow\Tests\Validator;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\WorkflowTest;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\SinglePlaceWorkflowValidator;
use Symfony\Component\Workflow\Workflow;

class SinglePlaceWorkflowValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage The marking store of workflow "foo" can not store many places.
     */
    public function testSinglePlaceWorkflowValidatorAndComplexWorkflow()
    {
        $definition = WorkflowTest::createComplexWorkflow();

        (new SinglePlaceWorkflowValidator())->validate($definition, 'foo');
    }

    public function testSinglePlaceWorkflowValidatorAndSimpleWorkflow()
    {
        $places = array('a', 'b');
        $transition = new Transition('t1', 'a', 'b');
        $definition = new Definition($places, array($transition));

        (new SinglePlaceWorkflowValidator())->validate($definition, 'foo');
    }
}
