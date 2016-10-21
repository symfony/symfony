<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\FactoryReturnTypePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\factoryFunction;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryParent;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
class FactoryReturnTypePassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $factory = $container->register('factory');
        $factory->setFactory(array(FactoryDummy::class, 'createFactory'));

        $container->setAlias('alias_factory', 'factory');

        $foo = $container->register('foo');
        $foo->setFactory(array(new Reference('alias_factory'), 'create'));

        $bar = $container->register('bar', __CLASS__);
        $bar->setFactory(array(new Reference('factory'), 'create'));

        $pass = new FactoryReturnTypePass();
        $pass->process($container);

        if (method_exists(\ReflectionMethod::class, 'getReturnType')) {
            $this->assertEquals(FactoryDummy::class, $factory->getClass());
            $this->assertEquals(\stdClass::class, $foo->getClass());
        } else {
            $this->assertNull($factory->getClass());
            $this->assertNull($foo->getClass());
        }
        $this->assertEquals(__CLASS__, $bar->getClass());
    }

    /**
     * @dataProvider returnTypesProvider
     */
    public function testReturnTypes($factory, $returnType, $hhvmSupport = true)
    {
        if (!$hhvmSupport && defined('HHVM_VERSION')) {
            $this->markTestSkipped('Scalar typehints not supported by hhvm.');
        }

        $container = new ContainerBuilder();

        $service = $container->register('service');
        $service->setFactory($factory);

        $pass = new FactoryReturnTypePass();
        $pass->process($container);

        if (method_exists(\ReflectionMethod::class, 'getReturnType')) {
            $this->assertEquals($returnType, $service->getClass());
        } else {
            $this->assertNull($service->getClass());
        }
    }

    public function returnTypesProvider()
    {
        return array(
            // must be loaded before the function as they are in the same file
            array(array(FactoryDummy::class, 'createBuiltin'), null, false),
            array(array(FactoryDummy::class, 'createParent'), FactoryParent::class),
            array(array(FactoryDummy::class, 'createSelf'), FactoryDummy::class),
            array(factoryFunction::class, FactoryDummy::class),
        );
    }

    public function testCircularReference()
    {
        $container = new ContainerBuilder();

        $factory = $container->register('factory');
        $factory->setFactory(array(new Reference('factory2'), 'createSelf'));

        $factory2 = $container->register('factory2');
        $factory2->setFactory(array(new Reference('factory'), 'create'));

        $pass = new FactoryReturnTypePass();
        $pass->process($container);

        $this->assertNull($factory->getClass());
        $this->assertNull($factory2->getClass());
    }

    public function testCompile()
    {
        $container = new ContainerBuilder();

        $factory = $container->register('factory');
        $factory->setFactory(array(FactoryDummy::class, 'createFactory'));

        if (!method_exists(\ReflectionMethod::class, 'getReturnType')) {
            $this->setExpectedException(\RuntimeException::class, 'Please add the class to service "factory" even if it is constructed by a factory since we might need to add method calls based on compile-time checks.');
        }

        $container->compile();

        $this->assertEquals(FactoryDummy::class, $container->getDefinition('factory')->getClass());
    }
}
