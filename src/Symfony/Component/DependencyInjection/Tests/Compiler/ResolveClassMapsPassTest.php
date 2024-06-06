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
use Symfony\Component\DependencyInjection\Argument\ClassMapArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassMapsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\AsFoo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Baz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Corge;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Foo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\FooEnum;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\FooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Qux;

class ResolveClassMapsPassTest extends TestCase
{
    private static ?string $fixturesPath = null;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = \dirname(__DIR__).'/Fixtures';
    }

    public static function tearDownAfterClass(): void
    {
        self::$fixturesPath = null;
    }

    /**
     * @dataProvider provideClassMapArgumentCases
     */
    public function testClassMapIsResolved(?string $instanceOf, ?string $withAttribute, ?string $indexBy, array $expected)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', self::$fixturesPath);
        $definition = $container->register('foo')->addArgument(new ClassMapArgument(
            'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid',
            '%kernel.project_dir%/ClassMap/Valid',
            $instanceOf,
            $withAttribute,
            $indexBy,
        ));

        (new ResolveClassMapsPass())->process($container);

        self::assertEquals($expected, $definition->getArgument(0));
    }

    public function provideClassMapArgumentCases(): iterable
    {
        yield [null, null, null, [
            0 => Bar::class,
            1 => Baz::class,
            2 => Corge::class,
            'foo-attribute' => Foo::class,
            3 => Qux::class,
            'foo-enum-attribute' => FooEnum::class,
        ]];

        yield [null, null, 'key', [
            'foo-attribute' => Foo::class,
            'bar-method' => Bar::class,
            'baz-prop' => Baz::class,
            'qux-const' => Qux::class,
            'corge-const' => Corge::class,
            'foo-enum-attribute' => FooEnum::class,
        ]];

        yield [FooInterface::class, null, null, [
            0 => Baz::class,
            1 => Corge::class,
            'foo-attribute' => Foo::class,
            'foo-enum-attribute' => FooEnum::class,
        ]];

        yield [null, AsFoo::class, 'key', [
            'bar-method' => Bar::class,
            'baz-prop' => Baz::class,
            'qux-const' => Qux::class,
        ]];

        yield [FooInterface::class, AsFoo::class, null, [
            0 => Baz::class,
        ]];
    }

    public function testClassMapIsResolvedWithMessage()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', self::$fixturesPath);
        $definition = $container->register('foo')->addArgument(new ClassMapArgument(
            'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\DifferentFilename',
            '%kernel.project_dir%/ClassMap/DifferentFilename',
        ));

        spl_autoload_register($autoload = function ($class) {
            if (str_ends_with($class, 'DifferentFilename\Foo_bar')) {
                throw new \RuntimeException('Error loading Foo_bar');
            }
        }, prepend: true);

        try {
            (new ResolveClassMapsPass())->process($container);
        } finally {
            spl_autoload_unregister($autoload);
        }

        self::assertCount(0, $definition->getArgument(0));
        self::assertCount(1, $definition->getErrors());
        self::assertStringMatchesFormat('Error loading Foo_bar', $definition->getErrors()[0]);
    }
}
