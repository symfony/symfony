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

    public function validate($value, Constraint $constraint)
    {
        $this->setMessage($this->message, $this->params);
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
     * @expectedException Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testSetMessageFailsIfNoContextSet()
    {
        $constraint = $this->getMock('Symfony\Component\Validator\Constraint', array(), array(), '', false);
        $validator = new ConstraintValidatorTest_Validator('error message', array('foo' => 'bar'));

        $validator->validate('bam', $constraint);
    }
}
