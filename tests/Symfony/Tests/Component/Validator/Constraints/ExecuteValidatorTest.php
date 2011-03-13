<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator;

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Constraints\Execute;
use Symfony\Component\Validator\Constraints\ExecuteValidator;

class ExecuteValidatorTest_Object
{
    public function validateOne(ExecutionContext $context)
    {
        $context->setCurrentClass('Foo');
        $context->setCurrentProperty('bar');
        $context->setGroup('mygroup');
        $context->setPropertyPath('foo.bar');

        $context->addViolation('My message', array('parameter'), 'invalidValue');
    }

    public function validateTwo(ExecutionContext $context)
    {
        $context->addViolation('Other message', array('other parameter'), 'otherInvalidValue');
    }
}

class ExecuteValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $walker;
    protected $context;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');

        $this->context = new ExecutionContext('Root', $this->walker, $metadataFactory);
        $this->context->setCurrentClass('InitialClass');
        $this->context->setCurrentProperty('initialProperty');
        $this->context->setGroup('InitialGroup');
        $this->context->setPropertyPath('initial.property.path');

        $this->validator = new ExecuteValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Execute('foo')));
    }

    public function testExecuteSingleMethod()
    {
        $object = new ExecuteValidatorTest_Object();

        $this->assertTrue($this->validator->isValid($object, new Execute('validateOne')));

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'My message',
            array('parameter'),
            'Root',
            'foo.bar',
            'invalidValue'
        ));

        $this->assertEquals($violations, $this->context->getViolations());
        $this->assertEquals('InitialClass', $this->context->getCurrentClass());
        $this->assertEquals('initialProperty', $this->context->getCurrentProperty());
        $this->assertEquals('InitialGroup', $this->context->getGroup());
        $this->assertEquals('initial.property.path', $this->context->getPropertyPath());
    }

    public function testExecuteMultipleMethods()
    {
        $object = new ExecuteValidatorTest_Object();

        $this->assertTrue($this->validator->isValid($object, new Execute(array(
            'validateOne', 'validateTwo'
        ))));

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'My message',
            array('parameter'),
            'Root',
            'foo.bar',
            'invalidValue'
        ));

        // context was reset
        $violations->add(new ConstraintViolation(
            'Other message',
            array('other parameter'),
            'Root',
            'initial.property.path',
            'otherInvalidValue'
        ));

        $this->assertEquals($violations, $this->context->getViolations());
    }
}