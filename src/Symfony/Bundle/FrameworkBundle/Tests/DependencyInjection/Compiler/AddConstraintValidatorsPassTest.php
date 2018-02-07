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
        $constraintValidatorFactoryDefinition = $container->register('validator.validator_factory')
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
            $constraintValidatorFactoryDefinition->getArgument(1)
        );
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoConstraintValidatorFactoryDefinition()
    {
        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process(new ContainerBuilder());

        // we just check that the pass does not fail if no constraint validator factory is registered
        $this->addToAssertionCount(1);
    }
}
