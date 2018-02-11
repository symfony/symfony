<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Validator\ConstraintValidatorFactory;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Blank as BlankConstraint;
use Symfony\Component\Validator\ConstraintValidator;

class ConstraintValidatorFactoryTest extends TestCase
{
    public function testGetInstanceCreatesValidator()
    {
        $class = get_class($this->getMockForAbstractClass('Symfony\\Component\\Validator\\ConstraintValidator'));

        $constraint = $this->getMockBuilder('Symfony\\Component\\Validator\\Constraint')->getMock();
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->will($this->returnValue($class));

        $factory = new ConstraintValidatorFactory(new Container());
        $this->assertInstanceOf($class, $factory->getInstance($constraint));
    }

    public function testGetInstanceReturnsExistingValidator()
    {
        $factory = new ConstraintValidatorFactory(new Container());
        $v1 = $factory->getInstance(new BlankConstraint());
        $v2 = $factory->getInstance(new BlankConstraint());
        $this->assertSame($v1, $v2);
    }

    public function testGetInstanceReturnsService()
    {
        $validator = new DummyConstraintValidator();

        $container = new Container();
        $container->set('validator_constraint_service', $validator);

        $factory = new ConstraintValidatorFactory($container, array('validator_constraint_alias' => 'validator_constraint_service'));
        $this->assertSame($validator, $factory->getInstance(new ConstraintStub()));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testGetInstanceInvalidValidatorClass()
    {
        $constraint = $this->getMockBuilder('Symfony\\Component\\Validator\\Constraint')->getMock();
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->will($this->returnValue('Fully\\Qualified\\ConstraintValidator\\Class\\Name'));

        $factory = new ConstraintValidatorFactory(new Container());
        $factory->getInstance($constraint);
    }
}

class ConstraintStub extends Constraint
{
    public function validatedBy()
    {
        return 'validator_constraint_alias';
    }
}

class DummyConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
    }
}
