<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\InterfaceInjector;
use Symfony\Component\DependencyInjection\Definition;

class InterfaceInjectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::addMethodCall
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::hasMethodCall
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::removeMethodCall
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::getMethodCalls
     *
     * @dataProvider getMethodCalls
     *
     * @param string $method
     * @param array $arguments
     */
    public function testAddRemoveGetMethodCalls($method, array $arguments = array())
    {
        $injector = new InterfaceInjector('stdClass');

        $injector->addMethodCall($method, $arguments);
        $this->assertTrue($injector->hasMethodCall($method), '->hasMethodCall() returns true for methods that were added on InterfaceInjector');

        $methodCalls = $injector->getMethodCalls();
        $this->assertEquals(1, count($methodCalls), '->getMethodCalls() returns array, where each entry is a method call');
        $this->assertEquals(array($method, $arguments), $methodCalls[0], '->getMethodCalls() has all methods added to InterfaceInjector instance');

        $injector->removeMethodCall($method);
        $this->assertFalse($injector->hasMethodCall($method), '->removeMethodClass() deletes the method call from InterfaceInjector');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::processDefinition
     *
     * @dataProvider getInjectorsAndDefinitions
     *
     * @param InterfaceInjector $injector
     * @param Definition $definition
     * @param int $expectedMethodsCount
     */
    public function testProcessDefinition(InterfaceInjector $injector, Definition $definition)
    {
        $injector->processDefinition($definition);
    }

    /**
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::supports
     *
     * @dataProvider getInjectorsAndClasses
     *
     * @param InterfaceInjector $injector
     * @param string $class
     * @param string $expectedResult
     */
    public function testSupports(InterfaceInjector $injector, $class, $expectedResult)
    {
        $this->assertEquals($expectedResult, $injector->supports($class), '->supports() must return true if injector is to be used on a class, false otherwise');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::processDefinition
     */
    public function testProcessesDefinitionOnlyOnce()
    {
        $injector = new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\Service');
        $injector->addMethodCall('method');

        $definition = $this->getMockDefinition('Symfony\Tests\Component\DependencyInjection\Service', 1);

        $injector->processDefinition($definition);
        $injector->processDefinition($definition);
    }

    /**
     * @covers Symfony\Component\DependencyInjection\InterfaceInjector::merge
     */
    public function testMerge()
    {
        $injector1 = new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\Service');
        $injector1->addMethodCall('method_one');

        $injector2 = new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\Service');
        $injector2->addMethodCall('method_two');

        $injector1->merge($injector2);

        $methodCalls = $injector1->getMethodCalls();
        $this->assertEquals(2, count($methodCalls));
        $this->assertEquals(array(
            array('method_one', array()),
            array('method_two', array()),
        ), $methodCalls);
    }

    /**
     * @expectedException Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testSupportsThrowsExceptionOnInvalidArgument()
    {
        $injector = new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\Service');
        $injector->supports(array());
    }

    public function getMethodCalls()
    {
        return array(
            array('method', array()),
            array('method2', array('one', 'two')),
            array('method3', array('single')),
        );
    }

    public function getInjectorsAndDefinitions()
    {
        $injector = new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\Service');
        $injector->addMethodCall('method');
        $injector->addMethodCall('method');
        $injector->addMethodCall('method');
        $injector->addMethodCall('method');

        $definition1 = $this->getMockDefinition('stdClass', 0);
        $definition2 = $this->getMockDefinition('Symfony\Tests\Component\DependencyInjection\Service', 4);

        return array(
            array($injector, $definition1),
            array($injector, $definition2),
        );
    }

    public function getInjectorsAndClasses()
    {
        return array(
            array(new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\Service'), 'Symfony\Tests\Component\DependencyInjection\Service', true),
            array(new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\SubService'), 'Symfony\Tests\Component\DependencyInjection\Service', false),
            array(new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\Service'), 'Symfony\Tests\Component\DependencyInjection\SubService', true),
            array(new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\SubService'), 'Symfony\Tests\Component\DependencyInjection\SubService', true),
            array(new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\FooInterface'), 'Symfony\Tests\Component\DependencyInjection\SubService', true),
            array(new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\FooInterface'), 'Symfony\Tests\Component\DependencyInjection\Service', false),
            array(new InterfaceInjector('Symfony\Tests\Component\DependencyInjection\FooInterface'), 'Symfony\Tests\Component\DependencyInjection\ServiceWithConstructor', false),
        );
    }

    /**
     * @param string $class
     * @param int $methodCount
     * @return Symfony\Component\DependencyInjection\Definition
     */
    private function getMockDefinition($class, $methodCount)
    {
        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $definition->expects($this->once())
            ->method('getClass')
            ->will($this->returnValue($class))
        ;
        $definition->expects($this->exactly($methodCount))
            ->method('addMethodCall')
        ;
        return $definition;
    }
}

class ServiceWithConstructor { public function __construct(\DateTime $required) {} }
class Service {}
class SubService extends Service implements FooInterface {}
interface FooInterface {}