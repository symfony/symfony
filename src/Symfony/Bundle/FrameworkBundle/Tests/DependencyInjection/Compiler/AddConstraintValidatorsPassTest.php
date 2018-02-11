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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @group legacy
 */
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
        $validatorFactory = $container->register('validator.validator_factory')
            ->addArgument(array());

        $container->register('my_abstract_constraint_validator')
            ->setAbstract(true)
            ->addTag('validator.constraint_validator');

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoConstraintValidatorFactoryDefinition()
    {
        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process(new ContainerBuilder());

        // we just check that the pass does not fail if no constraint validator factory is registered
        $this->addToAssertionCount(1);
    }
}
