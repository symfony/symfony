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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveNamedArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ResolveNamedArgumentsPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(array(
            2 => 'http://api.example.com',
            '$apiKey' => '123',
            0 => new Reference('foo'),
        ));
        $definition->addMethodCall('setApiKey', array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals(array(0 => new Reference('foo'), 1 => '123', 2 => 'http://api.example.com'), $definition->getArguments());
        $this->assertEquals(array(array('setApiKey', array('123'))), $definition->getMethodCalls());
    }

    public function testWithFactory(): void
    {
        $container = new ContainerBuilder();

        $container->register('factory', NoConstructor::class);
        $definition = $container->register('foo', NoConstructor::class)
            ->setFactory(array(new Reference('factory'), 'create'))
            ->setArguments(array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertSame(array(0 => '123'), $definition->getArguments());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     */
    public function testClassNull(): void
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class);
        $definition->setArguments(array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     */
    public function testClassNotExist(): void
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NotExist::class, NotExist::class);
        $definition->setArguments(array('$apiKey' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     */
    public function testClassNoConstructor(): void
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
    public function testArgumentNotFound(): void
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(array('$notFound' => '123'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    public function testTypedArgument(): void
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(array('$apiKey' => '123', CaseSensitiveClass::class => new Reference('foo')));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals(array(new Reference('foo'), '123'), $definition->getArguments());
    }
}

class NoConstructor
{
    public static function create($apiKey): void
    {
    }
}
