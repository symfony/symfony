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
use Symfony\Component\DependencyInjection\Tests\Fixtures\SimilarArgumentsDummy;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ResolveNamedArgumentsPassTest extends TestCase
{
    public function testProcess()
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

    public function testWithFactory()
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
    public function testClassNull()
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
    public function testClassNotExist()
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

    public function testTypedArgument()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(array('$apiKey' => '123', CaseSensitiveClass::class => new Reference('foo')));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals(array(new Reference('foo'), '123'), $definition->getArguments());
    }

    public function testResolvesMultipleArgumentsOfTheSameType()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(SimilarArgumentsDummy::class, SimilarArgumentsDummy::class);
        $definition->setArguments(array(CaseSensitiveClass::class => new Reference('foo'), '$token' => 'qwerty'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals(array(new Reference('foo'), 'qwerty', new Reference('foo')), $definition->getArguments());
    }

    public function testResolvePrioritizeNamedOverType()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(SimilarArgumentsDummy::class, SimilarArgumentsDummy::class);
        $definition->setArguments(array(CaseSensitiveClass::class => new Reference('foo'), '$token' => 'qwerty', '$class1' => new Reference('bar')));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals(array(new Reference('bar'), 'qwerty', new Reference('foo')), $definition->getArguments());
    }
}

class NoConstructor
{
    public static function create($apiKey)
    {
    }
}
