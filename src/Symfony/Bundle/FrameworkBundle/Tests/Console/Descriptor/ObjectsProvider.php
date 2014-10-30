<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ObjectsProvider
{
    public static function getRouteCollections()
    {
        $collection1 = new RouteCollection();
        foreach (self::getRoutes() as $name => $route) {
            $collection1->add($name, $route);
        }

        return array('route_collection_1' => $collection1);
    }

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

    public static function getContainerParameters()
    {
        return array(
            'parameters_1' => new ParameterBag(array(
                'integer' => 12,
                'string' => 'Hello world!',
                'boolean' => true,
                'array' => array(12, 'Hello world!', true),
            )),
        );
    }

    public static function getContainerParameter()
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('database_name', 'symfony');

        return array(
            'parameter' =>  $builder,
        );
    }

    public static function getContainerBuilders()
    {
        $builder1 = new ContainerBuilder();
        $builder1->setDefinitions(self::getContainerDefinitions());
        $builder1->setAliases(self::getContainerAliases());

        return array('builder_1' => $builder1);
    }

    public static function getContainerDefinitions()
    {
        $definition1 = new Definition('Full\\Qualified\\Class1');
        $definition2 = new Definition('Full\\Qualified\\Class2');

        return array(
            'definition_1' => $definition1
                ->setPublic(true)
                ->setSynthetic(false)
                ->setLazy(true)
                ->setSynchronized(true)
                ->setAbstract(true)
                ->setFactoryClass('Full\\Qualified\\FactoryClass')
                ->setFactoryMethod('get'),
            'definition_2' => $definition2
                ->setPublic(false)
                ->setSynthetic(true)
                ->setFile('/path/to/file')
                ->setLazy(false)
                ->setSynchronized(false)
                ->setAbstract(false)
                ->addTag('tag1', array('attr1' => 'val1', 'attr2' => 'val2'))
                ->addTag('tag1', array('attr3' => 'val3'))
                ->addTag('tag2')
                ->setFactoryService('factory.service')
                ->setFactoryMethod('get'),
        );
    }

    public static function getContainerAliases()
    {
        return array(
            'alias_1' => new Alias('service_1', true),
            'alias_2' => new Alias('service_2', false),
        );
    }
}
