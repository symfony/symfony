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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Blank as BlankConstraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;

class ContainerConstraintValidatorFactoryTest extends TestCase
{
    public function testGetInstanceCreatesValidator()
    {
        $class = get_class($this->getMockForAbstractClass(ConstraintValidator::class));

        $constraint = $this->getMockBuilder(Constraint::class)->getMock();
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->will($this->returnValue($class));

        $factory = new ContainerConstraintValidatorFactory(new Container());
        $this->assertInstanceOf($class, $factory->getInstance($constraint));
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
        $service = 'validator_constraint_service';
        $validator = $this->getMockForAbstractClass(ConstraintValidator::class);

        // mock ContainerBuilder b/c it implements TaggedContainerInterface
        $container = $this->getMockBuilder(ContainerBuilder::class)->setMethods(array('get', 'has'))->getMock();
        $container
            ->expects($this->once())
            ->method('get')
            ->with($service)
            ->willReturn($validator);
        $container
            ->expects($this->once())
            ->method('has')
            ->with($service)
            ->willReturn(true);

        $constraint = $this->getMockBuilder(Constraint::class)->getMock();
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->will($this->returnValue($service));

        $factory = new ContainerConstraintValidatorFactory($container);
        $this->assertSame($validator, $factory->getInstance($constraint));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
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
