<?php

namespace Symfony\Component\Workflow\Tests\Validator;

use Symfony\Component\Workflow\Tests\WorkflowBuilderTrait;
use Symfony\Component\Workflow\Validator\WorkflowValidator;

class WorkflowValidatorTest extends \PHPUnit_Framework_TestCase
{
    use WorkflowBuilderTrait;

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidDefinitionException
     * @expectedExceptionMessage The marking store of workflow "foo" can not store many places.
     */
    public function testSinglePlaceWorkflowValidatorAndComplexWorkflow()
    {
        $definition = $this->createComplexWorkflowDefinition();

        (new WorkflowValidator(true))->validate($definition, 'foo');
    }

    public function testSinglePlaceWorkflowValidatorAndSimpleWorkflow()
    {
        $definition = $this->createSimpleWorkflowDefinition();

        (new WorkflowValidator(true))->validate($definition, 'foo');
    }
}
