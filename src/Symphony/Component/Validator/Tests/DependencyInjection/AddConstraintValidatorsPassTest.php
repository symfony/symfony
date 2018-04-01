<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symphony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ServiceLocator;

class AddConstraintValidatorsPassTest extends TestCase
{
    public function testThatConstraintValidatorServicesAreProcessed()
    {
        $container = new ContainerBuilder();
        $validatorFactory = $container->register('validator.validator_factory')
            ->addArgument(array());

        $container->register('my_constraint_validator_service1', Validator1::class)
            ->addTag('validator.constraint_validator', array('alias' => 'my_constraint_validator_alias1'));
        $container->register('my_constraint_validator_service2', Validator2::class)
            ->addTag('validator.constraint_validator');

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);

        $expected = (new Definition(ServiceLocator::class, array(array(
            Validator1::class => new ServiceClosureArgument(new Reference('my_constraint_validator_service1')),
            'my_constraint_validator_alias1' => new ServiceClosureArgument(new Reference('my_constraint_validator_service1')),
            Validator2::class => new ServiceClosureArgument(new Reference('my_constraint_validator_service2')),
        ))))->addTag('container.service_locator')->setPublic(false);
        $this->assertEquals($expected, $container->getDefinition((string) $validatorFactory->getArgument(0)));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "my_abstract_constraint_validator" tagged "validator.constraint_validator" must not be abstract.
     */
    public function testAbstractConstraintValidator()
    {
        $container = new ContainerBuilder();
        $container->register('validator.validator_factory')
            ->addArgument(array());

        $container->register('my_abstract_constraint_validator')
            ->setAbstract(true)
            ->addTag('validator.constraint_validator');

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoConstraintValidatorFactoryDefinition()
    {
        $container = new ContainerBuilder();

        $definitionsBefore = count($container->getDefinitions());
        $aliasesBefore = count($container->getAliases());

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);

        // the container is untouched (i.e. no new definitions or aliases)
        $this->assertCount($definitionsBefore, $container->getDefinitions());
        $this->assertCount($aliasesBefore, $container->getAliases());
    }
}
