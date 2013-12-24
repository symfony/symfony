<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\ExpressionValidator;

class ExpressionValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new ExpressionValidator(PropertyAccess::createPropertyAccessor());
        $this->validator->initialize($this->context);

        $this->context->expects($this->any())
            ->method('getClassName')
            ->will($this->returnValue(__CLASS__));
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Expression('value == 1'));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Expression('value == 1'));
    }

    public function testSucceedingExpressionAtObjectLevel()
    {
        $constraint = new Expression('this.property == 1');

        $object = (object) array('property' => '1');

        $this->context->expects($this->any())
            ->method('getPropertyName')
            ->will($this->returnValue(null));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($object, $constraint);
    }

    public function testFailingExpressionAtObjectLevel()
    {
        $constraint = new Expression(array(
            'expression' => 'this.property == 1',
            'message' => 'myMessage',
        ));

        $object = (object) array('property' => '2');

        $this->context->expects($this->any())
            ->method('getPropertyName')
            ->will($this->returnValue(null));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->validator->validate($object, $constraint);
    }

    public function testSucceedingExpressionAtPropertyLevel()
    {
        $constraint = new Expression('value == this.expected');

        $object = (object) array('expected' => '1');

        $this->context->expects($this->any())
            ->method('getPropertyName')
            ->will($this->returnValue('property'));

        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('property'));

        $this->context->expects($this->any())
            ->method('getRoot')
            ->will($this->returnValue($object));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('1', $constraint);
    }

    public function testFailingExpressionAtPropertyLevel()
    {
        $constraint = new Expression(array(
            'expression' => 'value == this.expected',
            'message' => 'myMessage',
        ));

        $object = (object) array('expected' => '1');

        $this->context->expects($this->any())
            ->method('getPropertyName')
            ->will($this->returnValue('property'));

        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('property'));

        $this->context->expects($this->any())
            ->method('getRoot')
            ->will($this->returnValue($object));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->validator->validate('2', $constraint);
    }

    public function testSucceedingExpressionAtNestedPropertyLevel()
    {
        $constraint = new Expression('value == this.expected');

        $object = (object) array('expected' => '1');
        $root = (object) array('nested' => $object);

        $this->context->expects($this->any())
            ->method('getPropertyName')
            ->will($this->returnValue('property'));

        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('nested.property'));

        $this->context->expects($this->any())
            ->method('getRoot')
            ->will($this->returnValue($root));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('1', $constraint);
    }

    public function testFailingExpressionAtNestedPropertyLevel()
    {
        $constraint = new Expression(array(
            'expression' => 'value == this.expected',
            'message' => 'myMessage',
        ));

        $object = (object) array('expected' => '1');
        $root = (object) array('nested' => $object);

        $this->context->expects($this->any())
            ->method('getPropertyName')
            ->will($this->returnValue('property'));

        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('nested.property'));

        $this->context->expects($this->any())
            ->method('getRoot')
            ->will($this->returnValue($root));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $this->validator->validate('2', $constraint);
    }
}
