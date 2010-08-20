<?php

namespace Symfony\Tests\Component\Validator;

require_once __DIR__.'/Fixtures/InvalidConstraint.php';
require_once __DIR__.'/Fixtures/InvalidConstraintValidator.php';

use Symfony\Component\Validator\Extension\DependencyInjectionValidatorFactory;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraints\ValidValidator;
use Symfony\Tests\Component\Validator\Fixtures\InvalidConstraint;

class DependencyInjectionValidatorFactoryTest extends \PHPUnit_Framework_TestCase
{
    protected $container;
    protected $factory;

    public function setUp()
    {
        $this->container = new Container();
        $this->factory = new DependencyInjectionValidatorFactory($this->container);
    }

    public function tearDown()
    {
        unset ($this->factory);
        unset ($this->container);
    }

    public function testGetInstanceRetunsCorrectValidatorInstance()
    {
        $constraint = new Valid();
        $validator = $this->factory->getInstance($constraint);
        $this->assertTrue($validator instanceof ValidValidator);
    }

    public function testGetInstanceAddsValidatorServiceToContainer()
    {
        $constraint = new Valid();
        $validator = $this->factory->getInstance($constraint);
        $this->assertServiceExists('Symfony.Component.Validator.Constraints.ValidValidator');
    }

    public function assertServiceExists($id)
    {
        $this->assertTrue($this->container->has($id), 'Service ' . $id . ' doesn\'t exist on container');
    }

    /**
     * @expectedException LogicException
     */
    public function testGetInstanceThrowsLogicExceptionIfValidatorNotInstanceOfValidatorInterface()
    {
        $constraint = new InvalidConstraint();
        $validator = $this->factory->getInstance($constraint);
    }
}
