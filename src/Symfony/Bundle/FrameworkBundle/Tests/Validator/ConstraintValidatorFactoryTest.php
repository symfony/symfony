<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Bundle\FrameworkBundle\Validator\ConstraintValidatorFactory;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\Constraints\Blank as BlankConstraint;

class ConstraintValidatorFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetInstanceCreatesValidator()
    {
        $class = get_class($this->getMockForAbstractClass('Symfony\\Component\\Validator\\ConstraintValidator'));

        $constraint = $this->getMock('Symfony\\Component\\Validator\\Constraint');
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
        $service = 'validator_constraint_service';
        $alias = 'validator_constraint_alias';
        $validator = new \stdClass();

        // mock ContainerBuilder b/c it implements TaggedContainerInterface
        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerBuilder');
        $container
            ->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('validator.constraint_validator')
            ->will($this->returnValue(array(
                $service => array(array('alias' => $alias)),
            )));
        $container
            ->expects($this->once())
            ->method('get')
            ->with($service)
            ->will($this->returnValue($validator));

        $constraint = $this->getMock('Symfony\\Component\\Validator\\Constraint');
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->will($this->returnValue($alias));

        $factory = new ConstraintValidatorFactory($container);
        $factory->loadTaggedServiceIds($container);
        $this->assertSame($validator, $factory->getInstance($constraint));
    }
}
