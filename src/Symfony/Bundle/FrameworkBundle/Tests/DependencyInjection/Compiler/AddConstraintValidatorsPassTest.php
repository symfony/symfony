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

class AddConstraintValidatorsPassTest extends TestCase
{
    public function testThatConstraintValidatorServicesAreProcessed()
    {
        $services = array(
            'my_constraint_validator_service1' => array(0 => array('alias' => 'my_constraint_validator_alias1')),
            'my_constraint_validator_service2' => array(),
        );

        $validatorFactoryDefinition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('findTaggedServiceIds', 'getDefinition', 'hasDefinition'))->getMock();

        $validatorDefinition1 = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->setMethods(array('getClass'))->getMock();
        $validatorDefinition2 = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->setMethods(array('getClass'))->getMock();

        $validatorDefinition1->expects($this->atLeastOnce())
            ->method('getClass')
            ->willReturn('My\Fully\Qualified\Class\Named\Validator1');
        $validatorDefinition2->expects($this->atLeastOnce())
            ->method('getClass')
            ->willReturn('My\Fully\Qualified\Class\Named\Validator2');

        $container->expects($this->any())
            ->method('getDefinition')
            ->with($this->anything())
            ->will($this->returnValueMap(array(
                array('my_constraint_validator_service1', $validatorDefinition1),
                array('my_constraint_validator_service2', $validatorDefinition2),
                array('validator.validator_factory', $validatorFactoryDefinition),
            )));

        $container->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));
        $container->expects($this->atLeastOnce())
            ->method('hasDefinition')
            ->with('validator.validator_factory')
            ->will($this->returnValue(true));

        $validatorFactoryDefinition->expects($this->once())
            ->method('replaceArgument')
            ->with(1, array(
                'My\Fully\Qualified\Class\Named\Validator1' => 'my_constraint_validator_service1',
                'my_constraint_validator_alias1' => 'my_constraint_validator_service1',
                'My\Fully\Qualified\Class\Named\Validator2' => 'my_constraint_validator_service2',
            ));

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNoConstraintValidatorFactoryDefinition()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'findTaggedServiceIds', 'getDefinition'))->getMock();

        $container->expects($this->never())->method('findTaggedServiceIds');
        $container->expects($this->never())->method('getDefinition');
        $container->expects($this->atLeastOnce())
            ->method('hasDefinition')
            ->with('validator.validator_factory')
            ->will($this->returnValue(false));
        $definition->expects($this->never())->method('replaceArgument');

        $addConstraintValidatorsPass = new AddConstraintValidatorsPass();
        $addConstraintValidatorsPass->process($container);
    }
}
