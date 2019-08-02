<?php

namespace Symfony\Component\Workflow\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\WorkflowValidator;

class WorkflowValidatorTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testSinglePlaceWorkflowValidatorAndComplexWorkflow()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\InvalidDefinitionException');
        $this->expectExceptionMessage('The marking store of workflow "foo" can not store many places.');
        $definition = $this->createComplexWorkflowDefinition();

        (new WorkflowValidator(true))->validate($definition, 'foo');
    }

    public function testSinglePlaceWorkflowValidatorAndSimpleWorkflow()
    {
        $definition = $this->createSimpleWorkflowDefinition();

        (new WorkflowValidator(true))->validate($definition, 'foo');

        // the test ensures that the validation does not fail (i.e. it does not throw any exceptions)
        $this->addToAssertionCount(1);
    }

    public function testWorkflowWithInvalidNames()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\InvalidDefinitionException');
        $this->expectExceptionMessage('All transitions for a place must have an unique name. Multiple transitions named "t1" where found for place "a" in workflow "foo".');
        $places = range('a', 'c');

        $transitions = [];
        $transitions[] = new Transition('t0', 'c', 'b');
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'a', 'c');

        $definition = new Definition($places, $transitions);

        (new WorkflowValidator())->validate($definition, 'foo');
    }

    public function testSameTransitionNameButNotSamePlace()
    {
        $places = range('a', 'd');

        $transitions = [];
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'b', 'c');
        $transitions[] = new Transition('t1', 'd', 'c');

        $definition = new Definition($places, $transitions);

        (new WorkflowValidator())->validate($definition, 'foo');

        // the test ensures that the validation does not fail (i.e. it does not throw any exceptions)
        $this->addToAssertionCount(1);
    }
}
