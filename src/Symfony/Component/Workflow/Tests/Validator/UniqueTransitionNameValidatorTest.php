<?php

namespace Symfony\Component\Workflow\Tests\Validator;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\UniqueTransitionNameValidator;

class UniqueTransitionNameValidatorTest extends \PHPUnit_Framework_TestCase
{
    use WorkflowBuilderTrait;

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage All transitions for a place must have an unique name. Multiple transitions named "t1" where found for place "a" in workflow "foo".
     */
    public function testWorkflowWithInvalidNames()
    {
        $places = range('a', 'c');

        $transitions = array();
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'a', 'c');

        $definition = new Definition($places, $transitions);

        (new UniqueTransitionNameValidator())->validate($definition, 'foo');
    }

    public function testSameTransitionNameButNotSamePlace()
    {
        $places = range('a', 'd');

        $transitions = array();
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'b', 'c');
        $transitions[] = new Transition('t1', 'd', 'c');

        $definition = new Definition($places, $transitions);

        (new UniqueTransitionNameValidator())->validate($definition, 'foo');
    }

    public function testValidWorkflow()
    {
        $definition = $this->createSimpleWorkflowDefinition();

        (new UniqueTransitionNameValidator())->validate($definition, 'foo');
    }
}
