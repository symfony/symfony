<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Container;
use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\Constraints\Blank as BlankConstraint;
use Symphony\Component\Validator\ConstraintValidator;
use Symphony\Component\Validator\ContainerConstraintValidatorFactory;

class ContainerConstraintValidatorFactoryTest extends TestCase
{
    public function testGetInstanceCreatesValidator()
    {
        $factory = new ContainerConstraintValidatorFactory(new Container());
        $this->assertInstanceOf(DummyConstraintValidator::class, $factory->getInstance(new DummyConstraint()));
    }

    public function testGetInstanceReturnsExistingValidator()
    {
        $factory = new ContainerConstraintValidatorFactory(new Container());
        $v1 = $factory->getInstance(new BlankConstraint());
        $v2 = $factory->getInstance(new BlankConstraint());
        $this->assertSame($v1, $v2);
    }

    public function testGetInstanceReturnsService()
    {
        $validator = new DummyConstraintValidator();
        $container = new Container();
        $container->set(DummyConstraintValidator::class, $validator);

        $factory = new ContainerConstraintValidatorFactory($container);

        $this->assertSame($validator, $factory->getInstance(new DummyConstraint()));
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\ValidatorException
     */
    public function testGetInstanceInvalidValidatorClass()
    {
        $constraint = $this->getMockBuilder(Constraint::class)->getMock();
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->will($this->returnValue('Fully\\Qualified\\ConstraintValidator\\Class\\Name'));

        $factory = new ContainerConstraintValidatorFactory(new Container());
        $factory->getInstance($constraint);
    }
}

class DummyConstraint extends Constraint
{
    public function validatedBy()
    {
        return DummyConstraintValidator::class;
    }
}

class DummyConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
    }
}
