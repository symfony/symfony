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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Blank as BlankConstraint;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Tests\Fixtures\DummyConstraint;
use Symfony\Component\Validator\Tests\Fixtures\DummyConstraintValidator;

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

    public function testGetInstanceInvalidValidatorClass()
    {
        $this->expectException(ValidatorException::class);
        $constraint = $this->createMock(Constraint::class);
        $constraint
            ->expects($this->once())
            ->method('validatedBy')
            ->willReturn('Fully\\Qualified\\ConstraintValidator\\Class\\Name');

        $factory = new ContainerConstraintValidatorFactory(new Container());
        $factory->getInstance($constraint);
    }
}
