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
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveNamedArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummyWithoutReturnTypes;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsVariadicsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\SimilarArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition1;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ResolveNamedArgumentsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments([
            2 => 'http://api.example.com',
            '$apiKey' => '123',
            0 => new Reference('foo'),
        ]);
        $definition->addMethodCall('setApiKey', ['$apiKey' => '123']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals([0 => new Reference('foo'), 1 => '123', 2 => 'http://api.example.com'], $definition->getArguments());
        $this->assertEquals([['setApiKey', ['123']]], $definition->getMethodCalls());
    }

    public function testWithFactory()
    {
        $container = new ContainerBuilder();

        $container->register('factory', NoConstructor::class);
        $definition = $container->register('foo', NoConstructor::class)
            ->setFactory([new Reference('factory'), 'create'])
            ->setArguments(['$apiKey' => '123']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertSame([0 => '123'], $definition->getArguments());
    }

    public function testClassNull()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\RuntimeException');
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class);
        $definition->setArguments(['$apiKey' => '123']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    public function testClassNotExist()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\RuntimeException');
        $container = new ContainerBuilder();

        $definition = $container->register(NotExist::class, NotExist::class);
        $definition->setArguments(['$apiKey' => '123']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    public function testClassNoConstructor()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\RuntimeException');
        $container = new ContainerBuilder();

        $definition = $container->register(NoConstructor::class, NoConstructor::class);
        $definition->setArguments(['$apiKey' => '123']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    public function testArgumentNotFound()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid service "Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy": method "__construct()" has no argument named "$notFound". Check your service definition.');
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(['$notFound' => '123']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    public function testCorrectMethodReportedInException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid service "Symfony\Component\DependencyInjection\Tests\Fixtures\TestDefinition1": method "Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummyWithoutReturnTypes::createTestDefinition1()" has no argument named "$notFound". Check your service definition.');
        $container = new ContainerBuilder();

        $container->register(FactoryDummyWithoutReturnTypes::class, FactoryDummyWithoutReturnTypes::class);

        $definition = $container->register(TestDefinition1::class, TestDefinition1::class);
        $definition->setFactory([FactoryDummyWithoutReturnTypes::class, 'createTestDefinition1']);
        $definition->setArguments(['$notFound' => '123']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    public function testTypedArgument()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArguments(['$apiKey' => '123', CaseSensitiveClass::class => new Reference('foo')]);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals([new Reference('foo'), '123'], $definition->getArguments());
    }

    public function testTypedArgumentWithMissingDollar()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid service "Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy": did you forget to add the "$" prefix to argument "apiKey"?');
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArgument('apiKey', '123');

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);
    }

    public function testInterfaceTypedArgument()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsDummy::class, NamedArgumentsDummy::class);
        $definition->setArgument(ContainerInterface::class, $expected = new Reference('foo'));

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertSame($expected, $definition->getArgument(3));
    }

    public function testResolvesMultipleArgumentsOfTheSameType()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(SimilarArgumentsDummy::class, SimilarArgumentsDummy::class);
        $definition->setArguments([CaseSensitiveClass::class => new Reference('foo'), '$token' => 'qwerty']);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals([new Reference('foo'), 'qwerty', new Reference('foo')], $definition->getArguments());
    }

    public function testResolvePrioritizeNamedOverType()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(SimilarArgumentsDummy::class, SimilarArgumentsDummy::class);
        $definition->setArguments([CaseSensitiveClass::class => new Reference('foo'), '$token' => 'qwerty', '$class1' => new Reference('bar')]);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals([new Reference('bar'), 'qwerty', new Reference('foo')], $definition->getArguments());
    }

    public function testVariadics()
    {
        $container = new ContainerBuilder();

        $definition = $container->register(NamedArgumentsVariadicsDummy::class, NamedArgumentsVariadicsDummy::class);
        $definition->setArguments([
            '$class' => new \stdClass(),
            '$variadics' => [
                new Reference('foo'),
                new Reference('bar'),
                new Reference('baz'),
            ],
        ]);

        $pass = new ResolveNamedArgumentsPass();
        $pass->process($container);

        $this->assertEquals(
            [
                0 => new \stdClass(),
                1 => new Reference('foo'),
                2 => new Reference('bar'),
                3 => new Reference('baz'),
            ],
            $definition->getArguments()
        );
    }
}

class NoConstructor
{
    public static function create($apiKey)
    {
    }
}
