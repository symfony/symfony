<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\ClassMapLoader;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\AsFoo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Baz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Corge;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Foo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\FooEnum;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\FooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\Qux;

class ClassMapLoaderTest extends TestCase
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
     * @dataProvider provideResourceCases
     */
    public function testLoad(array $resource, array $expected)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', self::$fixturesPath);

        $loader = new ClassMapLoader($container, new FileLocator());

        self::assertSame($expected, $loader->load($resource));

        $resources = $container->getResources();
        self::assertInstanceOf(GlobResource::class, $globResource = end($resources));
        self::assertStringMatchesFormat('glob.%s/ClassMap/%s', (string) $globResource);
    }

    public function provideResourceCases(): iterable
    {
        $resource = [
            'namespace' => 'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid\\',
            'path' => '%kernel.project_dir%/ClassMap/Valid',
        ];

        yield [$resource, [
            Bar::class => null,
            Baz::class => null,
            Corge::class => null,
            Foo::class => null,
            FooEnum::class => null,
            Qux::class => null,
        ]];

        yield [[...$resource, 'instance_of' => FooInterface::class], [
            Baz::class => null,
            Corge::class => null,
            Foo::class => null,
            FooEnum::class => null,
        ]];

        yield [[...$resource, 'with_attribute' => AsFoo::class], [
            Bar::class => null,
            Baz::class => null,
            Qux::class => null,
        ]];

        yield [[...$resource, 'instance_of' => FooInterface::class, 'with_attribute' => AsFoo::class], [
            Baz::class => null,
        ]];

        yield [[
            'namespace' => 'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\NotPhp\\',
            'path' => '%kernel.project_dir%/ClassMap/NotPhp',
        ], []];

        yield [[
            'namespace' => 'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\InvalidClassName\\',
            'path' => '%kernel.project_dir%/ClassMap/InvalidClassName',
        ], []];
    }

    public function testLoadErrorMessage()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', self::$fixturesPath);

        spl_autoload_register($autoload = function ($class) {
            if (str_ends_with($class, 'DifferentFilename\Foo_bar')) {
                throw new \RuntimeException('Error loading Foo_bar');
            }
        }, prepend: true);

        try {
            $loader = new ClassMapLoader($container, new FileLocator());
            $classMap = $loader->load([
                'namespace' => 'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\DifferentFilename\\',
                'path' => '%kernel.project_dir%/ClassMap/DifferentFilename',
            ]);
        } finally {
            spl_autoload_unregister($autoload);
        }

        self::assertCount(1, $classMap);
        self::assertArrayHasKey($key = 'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\DifferentFilename\Foo_bar', $classMap);
        self::assertStringMatchesFormat('Error loading Foo_bar', $classMap[$key]);

        $resources = $container->getResources();
        self::assertInstanceOf(GlobResource::class, $globResource = end($resources));
        self::assertStringMatchesFormat('glob.%s/ClassMap/%s', (string) $globResource);
    }

    public function testLoadThrowsExceptionOnInvalidNamespace()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', self::$fixturesPath);

        $loader = new ClassMapLoader($container, new FileLocator());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Expected to find class ".+" in file ".+" while importing class names from resource ".+", but it was not found! Check the namespace prefix used with the resource./');

        $loader->load([
            'namespace' => 'Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\InvalidNamespace\\',
            'path' => '%kernel.project_dir%/ClassMap/DifferentFilename',
        ]);
    }

    /**
     * @testWith [{"namespace": "Foo", "path": "bar"}, "class-map", true]
     *           [{"namespace": "Foo"}, "class-map", false]
     *           [{"path": "bar"}, "class-map", false]
     *           [{"namespace": "Foo", "path": "bar"}, null, false]
     *           [null, "class-map", false]
     *           [{}, "class-map", false]
     */
    public function testSupports(mixed $resource, ?string $type, bool $expected)
    {
        $loader = new ClassMapLoader(new ContainerBuilder(), new FileLocator());

        self::assertSame($expected, $loader->supports($resource, $type));
    }
}
