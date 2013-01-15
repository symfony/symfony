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

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AllValidator;

class AllValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new AllValidator();
        $this->validator->initialize($this->context);

        $this->context->expects($this->any())
            ->method('getGroup')
            ->will($this->returnValue('MyGroup'));
    }

    protected function tearDown()
    {
        $this->validator = null;
        $this->context = null;
    }

    public function deprecationErrorHandler($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        set_error_handler(array($this, "deprecationErrorHandler"));
        $this->validator->validate(null, new All(new Range(array('min' => 4))));
        restore_error_handler();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        set_error_handler(array($this, "deprecationErrorHandler"));
        $this->validator->validate('foo.barbar', new All(new Range(array('min' => 4))));
        restore_error_handler();
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($array)
    {
        set_error_handler(array($this, "deprecationErrorHandler"));
        $constraint = new Range(array('min' => 4));
        restore_error_handler();

        $i = 1;

        foreach ($array as $key => $value) {
            $this->context->expects($this->at($i++))
                ->method('validateValue')
                ->with($value, $constraint, '['.$key.']', 'MyGroup');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($array, new All($constraint));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($array)
    {
        set_error_handler(array($this, "deprecationErrorHandler"));
        $constraint1 = new Range(array('min' => 4));
        $constraint2 = new NotNull();
        restore_error_handler();

        $constraints = array($constraint1, $constraint2);
        $i = 1;

        foreach ($array as $key => $value) {
            $this->context->expects($this->at($i++))
                ->method('validateValue')
                ->with($value, $constraint1, '['.$key.']', 'MyGroup');
            $this->context->expects($this->at($i++))
                ->method('validateValue')
                ->with($value, $constraint2, '['.$key.']', 'MyGroup');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($array, new All($constraints));
    }

    public function getValidArguments()
    {
        return array(
            array(array(5, 6, 7)),
            array(new \ArrayObject(array(5, 6, 7))),
        );
    }
}
