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

use Symfony\Component\DependencyInjection\Compiler\ResolveNamedArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ResolveNamedArgumentsPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(array(0 => new Reference('foo'), '$apiKey' => '123'));
        $definition->addMethodCall('setApiKey', array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals(array(0 => new Reference('foo'), 1 => '123'), $definition->getArguments());
        $this->assertEquals(array(array('setApiKey', array('123'))), $definition->getMethodCalls());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testClassNull()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class);
        $definition->setArguments(array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testClassNotExist()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NotExist::class, NotExist::class);
        $definition->setArguments(array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testClassNoConstructor()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NoConstructor::class, NoConstructor::class);
        $definition->setArguments(array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testArgumentNotFound()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(array('$notFound' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }
}

class NoConstructor
{
}
