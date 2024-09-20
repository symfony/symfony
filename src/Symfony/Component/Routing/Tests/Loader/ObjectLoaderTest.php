<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Loader\ObjectLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ObjectLoaderTest extends TestCase
{
    public function testLoadCallsServiceAndReturnsCollection()
    {
        $loader = new TestObjectLoader('some-env');

        // create a basic collection that will be returned
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $loader->loaderMap = [
            'my_route_provider_service' => new TestObjectLoaderRouteService($collection, 'some-env'),
        ];

        $actualRoutes = $loader->load(
            'my_route_provider_service::loadRoutes',
            'service'
        );

        $this->assertSame($collection, $actualRoutes);
        // the service file should be listed as a resource
        $this->assertNotEmpty($actualRoutes->getResources());
    }

    /**
     * @dataProvider getBadResourceStrings
     */
    public function testExceptionWithoutSyntax(string $resourceString)
    {
        $loader = new TestObjectLoader();

        $this->expectException(\InvalidArgumentException::class);

        $loader->load($resourceString);
    }

    public static function getBadResourceStrings()
    {
        return [
            ['Foo:Bar:baz'],
            ['Foo::Bar::baz'],
            ['Foo:'],
            ['Foo::'],
            [':Foo'],
            ['::Foo'],
        ];
    }

    public function testExceptionOnNoObjectReturned()
    {
        $loader = new TestObjectLoader();
        $loader->loaderMap = ['my_service' => 'NOT_AN_OBJECT'];

        $this->expectException(\TypeError::class);

        $loader->load('my_service::method');
    }

    public function testExceptionOnBadMethod()
    {
        $loader = new TestObjectLoader();
        $loader->loaderMap = ['my_service' => new \stdClass()];

        $this->expectException(\BadMethodCallException::class);

        $loader->load('my_service::method');
    }

    public function testExceptionOnMethodNotReturningCollection()
    {
        $service = $this->createMock(CustomRouteLoader::class);

        $service->expects($this->once())
            ->method('loadRoutes')
            ->willReturn('NOT_A_COLLECTION');

        $loader = new TestObjectLoader();
        $loader->loaderMap = ['my_service' => $service];

        $this->expectException(\LogicException::class);

        $loader->load('my_service::loadRoutes');
    }
}

class TestObjectLoader extends ObjectLoader
{
    public array $loaderMap = [];

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'service';
    }

    protected function getObject(string $id): object
    {
        return $this->loaderMap[$id];
    }
}

interface CustomRouteLoader
{
    public function loadRoutes();
}

class TestObjectLoaderRouteService
{
    private RouteCollection $collection;
    private ?string $env;

    public function __construct($collection, ?string $env = null)
    {
        $this->collection = $collection;
        $this->env = $env;
    }

    public function loadRoutes(TestObjectLoader $loader, ?string $env = null)
    {
        if ($this->env !== $env) {
            throw new \InvalidArgumentException(\sprintf('Expected env "%s", "%s" given.', $this->env, $env));
        }

        return $this->collection;
    }
}
