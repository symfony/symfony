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
        $validator = $this->getMockForAbstractClass('Symfony\\Component\\Validator\\ConstraintValidator');

        // mock ContainerBuilder b/c it implements TaggedContainerInterface
        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerBuilder', array('get'));
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

        $factory = new ConstraintValidatorFactory($container, array('validator_constraint_alias' => 'validator_constraint_service'));
        $this->assertSame($validator, $factory->getInstance($constraint));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testGetInstanceInvalidValidatorClass()
    {
        $constraint = $this->getMock('Symfony\\Component\\Validator\\Constraint');
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->will($this->returnValue('Fully\\Qualified\\ConstraintValidator\\Class\\Name'));

        $factory = new ConstraintValidatorFactory(new Container());
        $factory->getInstance($constraint);
    }
}
