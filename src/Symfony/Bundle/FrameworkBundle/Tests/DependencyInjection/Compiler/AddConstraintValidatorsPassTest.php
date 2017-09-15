<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConstraintValidatorsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AddConstraintValidatorsPassTest extends TestCase
{
    public function testThatConstraintValidatorServicesAreProcessed()
    {
        $container = new ContainerBuilder();
        $validatorFactoryDefinition = $container->register('validator.validator_factory')
            ->setArguments(array(new Reference('service_container'), array()));

        $container->register('my_constraint_validator_service1', 'My\Fully\Qualified\Class\Named\Validator1')
            ->addTag('validator.constraint_validator', array('alias' => 'my_constraint_validator_alias1'));
        $container->register('my_constraint_validator_service2', 'My\Fully\Qualified\Class\Named\Validator2')
            ->addTag('validator.constraint_validator');

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);

        $this->assertEquals(
            array(
                'My\Fully\Qualified\Class\Named\Validator1' => 'my_constraint_validator_service1',
                'my_constraint_validator_alias1' => 'my_constraint_validator_service1',
                'My\Fully\Qualified\Class\Named\Validator2' => 'my_constraint_validator_service2',
            ),
            $validatorFactoryDefinition->getArgument(1)
        );
    }

    public function testThatConstraintValidatorServicesAreProcessedIfTheValidatorFactoryIsDecorated()
    {
        $container = new ContainerBuilder();
        $validatorFactoryDefinition = $container->register('validator_factory_decorator.inner')
            ->setArguments(array(new Reference('service_container'), array()));
        $container->register('validator_factory_decorator')->setArguments(array(new Reference('validator_factory_decorator.inner')));
        $container->setAlias('validator.validator_factory', 'validator_factory_decorator.inner');

        $container->register('my_constraint_validator_service1', 'My\Fully\Qualified\Class\Named\Validator1')
            ->addTag('validator.constraint_validator', array('alias' => 'my_constraint_validator_alias1'));
        $container->register('my_constraint_validator_service2', 'My\Fully\Qualified\Class\Named\Validator2')
            ->addTag('validator.constraint_validator');

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);

        $this->assertEquals(
            array(
                'My\Fully\Qualified\Class\Named\Validator1' => 'my_constraint_validator_service1',
                'my_constraint_validator_alias1' => 'my_constraint_validator_service1',
                'My\Fully\Qualified\Class\Named\Validator2' => 'my_constraint_validator_service2',
            ),
            $validatorFactoryDefinition->getArgument(1)
        );
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoConstraintValidatorFactoryDefinition()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('has', 'findTaggedServiceIds', 'getDefinition'))->getMock();

        $container->expects($this->never())->method('findTaggedServiceIds');
        $container->expects($this->never())->method('getDefinition');
        $container->expects($this->atLeastOnce())
            ->method('has')
            ->with('validator.validator_factory')
            ->will($this->returnValue(false));
        $definition->expects($this->never())->method('replaceArgument');

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);
    }
}
