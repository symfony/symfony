<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

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
}
