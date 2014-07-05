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

use Symfony\Component\DependencyInjection\Compiler\CheckServiceClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CheckServiceClassPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\BadServiceClassException
     * @expectedExceptionMessage Class "\InvalidName\TestClass" not found. Check the spelling on the "class" configuration for your "a" service.
     */
    public function testThrowsBadNameExceptionWhenServiceHasInvalidClassName()
    {
        $container = new ContainerBuilder();
        $container->register('a', '\InvalidName\TestClass');

        $this->process($container);
    }

    public function testDoesNotThrowExceptionIfClassExists()
    {
        $container = new ContainerBuilder();
        $container->register('a', '\Symfony\Component\DependencyInjection\Compiler\CheckServiceClassPass');
        $container->register('b', '\stdClass');
        $container->register('c', '\Symfony\Component\DependencyInjection\Tests\Compiler\MyTestService');
        $container
            ->register('d', '\stdClass')
            ->setFactoryService('\Symfony\Component\DependencyInjection\Tests\Compiler\MyTestService')
            ->setFactoryMethod('factoryMethod')
        ;

        $this->process($container);
    }

    public function testSynteticServicesClassNamesAreNotChecked()
    {
        $container = new ContainerBuilder();
        $container
            ->register('a', '\InvalidName\TestClass')
            ->setSynthetic(true)
        ;

        $this->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\BadServiceClassException
     * @expectedExceptionMessage Class "\InvalidName\TestClass" not found. Check the spelling on the "factory_class" configuration for your "a" service.
     */
    public function testFactoryMethodServicesClassNamesAreNotChecked()
    {
        $container = new ContainerBuilder();
        $container
            ->register('a', '\stdClass')
            ->setFactoryClass('\InvalidName\TestClass')
            ->setFactoryMethod('factoryMethod')
        ;

        $this->process($container);
    }

    public function testServicesWithoutNameAreNotChecked()
    {
        $container = new ContainerBuilder();
        $container
            ->register('a')
        ;

        $this->process($container);
    }

    public function testSynteticServicesNameAreNotChecked()
    {
        $container = new ContainerBuilder();
        $container
            ->register('a', '\InvalidName\TestClass')
            ->setSynthetic(true)
        ;

        $this->process($container);
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new CheckServiceClassPass();
        $pass->process($container);
    }
}

class MyTestService
{
    public function factoryMethod()
    {
    }
}
