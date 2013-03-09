<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ConstraintValidatorTest_Validator extends ConstraintValidator
{
    private $message;
    private $params;

    public function __construct($message, array $params = array())
    {
        $this->message = $message;
        $this->params = $params;
    }

    public function deprecationErrorHandler($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
    }

    public function validate($value, Constraint $constraint)
    {
        set_error_handler(array($this, "deprecationErrorHandler"));
        $this->setMessage($this->message, $this->params);
        restore_error_handler();
    }
}

class ConstraintValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function testSetMessage()
    {
        $context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $constraint = $this->getMock('Symfony\Component\Validator\Constraint', array(), array(), '', false);
        $validator = new ConstraintValidatorTest_Validator('error message', array('foo' => 'bar'));
        $validator->initialize($context);

        $context->expects($this->once())
            ->method('addViolation')
            ->with('error message', array('foo' => 'bar'));

        $validator->validate('bam', $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testSetMessageFailsIfNoContextSet()
    {
        $constraint = $this->getMock('Symfony\Component\Validator\Constraint', array(), array(), '', false);
        $validator = new ConstraintValidatorTest_Validator('error message', array('foo' => 'bar'));

        $validator->validate('bam', $constraint);
    }
}
