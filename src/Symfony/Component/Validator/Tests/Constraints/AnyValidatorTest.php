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

use Symfony\Component\Validator\Constraints\Any;
use Symfony\Component\Validator\Constraints\AnyValidator;
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContext;

/**
 * @author Cas Leentfaar <info@casleentfaar.com>
 */
class AnyValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ExecutionContext
     */
    protected $context;

    /**
     * @var AnyValidator
     */
    protected $validator;

    protected function setUp()
    {
        $this->context   = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new AnyValidator();
        $this->validator->initialize($this->context);

        $this->context->expects($this->any())
            ->method('getGroup')
            ->will($this->returnValue('MyGroup'));
    }

    protected function tearDown()
    {
        $this->validator = null;
        $this->context   = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Any(new Range(array('min' => 4))));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($value)
    {
        $constraint = new Range(array('min' => 4));

        $this->context->expects($this->once())
            ->method('validateValue')
            ->with($value, $constraint, '', 'MyGroup');
        $this->context->expects($this->any())
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($value, new Any($constraint));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($value)
    {
        $constraint1 = new Range(array('min' => 4));
        $constraint2 = new NotNull();

        $constraints = array($constraint1, $constraint2);
        $i           = 1;

        foreach ($constraints as $constraint) {
            $this->context->expects($this->at($i++))
                ->method('validateValue')
                ->with($value, $constraint, '', 'MyGroup');
            $this->context->expects($this->at($i++))
                ->method('getViolations')
                ->willReturn(new ConstraintViolationList());
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($value, new Any($constraints));
    }

    public function testNoConstraintValidated()
    {
        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Isbn();
        $value = 'bla';

        $constraints = array($constraint1, $constraint2);

        $i = 1;
        foreach ($constraints as $constraint) {
            $this->context->expects($this->at($i++))
                ->method('validateValue')
                ->with($value, $constraint, '', 'MyGroup');
            $this->context->expects($this->at($i++))
                ->method('getViolations');
        }

        $this->context->expects($this->once())
            ->method('addViolation');

        $this->validator->validate($value, new Any($constraints));
    }

    /**
     * @return array
     */
    public function getValidArguments()
    {
        return array(
            array(5),
            array(6),
            array(7),
        );
    }
}
