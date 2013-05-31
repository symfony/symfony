<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ObjectsProvider
{
    /** @return RouteCollection[] */
    public static function getRouteCollections()
    {
        $collection1 = new RouteCollection();
        foreach (self::getRoutes() as $name => $route) {
            $collection1->add($name, $route);
        }

        return array('route_collection_1' => $collection1);
    }

    /** @return Route[] */
    public static function getRoutes()
    {
        return array(
            'route_1' => new Route(
                '/hello/{name}',
                array('name' => 'Joseph'),
                array('name' => '[a-z]+'),
                array('opt1' => 'val1', 'opt2' => 'val2'),
                'localhost',
                array('http', 'https'),
                array('get', 'head')
            ),
            'route_2' => new Route(
                '/name/add',
                array(),
                array(),
                array('opt1' => 'val1', 'opt2' => 'val2'),
                'localhost',
                array('http', 'https'),
                array('put', 'post')
            ),
        );
    }

    /** @return Alias[][] */
    public static function getContainerServices()
    {
        $service1 = new Definition('Full\\Qualified\\Class1');
        $service2 = new Definition('Full\\Qualified\\Class2');

        return array(
            'service_1' => $service1
                ->setPublic(true)
                ->setSynthetic(false),
            'service_2' => $service2
                ->setPublic(false)
                ->setSynthetic(true)
                ->setFile('/path/to/file')
                ->addTag('tag1', array('attr1' => 'val1', 'attr2' => 'val2'))
                ->addTag('tag1', array('attr3' => 'val3'))
                ->addTag('tag2'),
        );
    }

    /** @return Alias[][] */
    public static function getContainerAliases()
    {
        return array(
            'alias_1' => new Alias('service_1', true),
            'alias_2' => new Alias('service_2', false),
        );
    }
}
