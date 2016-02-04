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

use Symfony\Component\Routing\Loader\ObjectRouteLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ObjectRouteLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadCallsServiceAndReturnsCollection()
    {
        $loader = new ObjectRouteLoaderForTest();

        // create a basic collection that will be returned
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $loader->loaderMap = array(
            'my_route_provider_service' => new RouteService($collection),
        );

        $actualRoutes = $loader->load(
            'my_route_provider_service:loadRoutes',
            'service'
        );

        $this->assertSame($collection, $actualRoutes);
        // the service file should be listed as a resource
        $this->assertNotEmpty($actualRoutes->getResources());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider getBadResourceStrings
     */
    public function testExceptionWithoutSyntax($resourceString)
    {
        $loader = new ObjectRouteLoaderForTest();
        $loader->load($resourceString);
    }

    public function getBadResourceStrings()
    {
        return array(
            array('Foo'),
            array('Bar::baz'),
            array('Foo:Bar:baz'),
        );
    }

    /**
     * @expectedException \LogicException
     */
    public function testExceptionOnNoObjectReturned()
    {
        $loader = new ObjectRouteLoaderForTest();
        $loader->loaderMap = array('my_service' => 'NOT_AN_OBJECT');
        $loader->load('my_service:method');
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testExceptionOnBadMethod()
    {
        $loader = new ObjectRouteLoaderForTest();
        $loader->loaderMap = array('my_service' => new \stdClass());
        $loader->load('my_service:method');
    }

    /**
     * @expectedException \LogicException
     */
    public function testExceptionOnMethodNotReturningCollection()
    {
        $service = $this->getMockBuilder('stdClass')
            ->setMethods(array('loadRoutes'))
            ->getMock();
        $service->expects($this->once())
            ->method('loadRoutes')
            ->will($this->returnValue('NOT_A_COLLECTION'));

        $loader = new ObjectRouteLoaderForTest();
        $loader->loaderMap = array('my_service' => $service);
        $loader->load('my_service:loadRoutes');
    }
}

class ObjectRouteLoaderForTest extends ObjectRouteLoader
{
    public $loaderMap = array();

    protected function getServiceObject($id)
    {
        return isset($this->loaderMap[$id]) ? $this->loaderMap[$id] : null;
    }
}

class RouteService
{
    private $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    public function loadRoutes()
    {
        return $this->collection;
    }
}
