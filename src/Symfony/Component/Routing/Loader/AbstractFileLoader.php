<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * AbstractFileLoader loads routing files.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
abstract class AbstractFileLoader extends FileLoader
{

    /**
     * Parses a configuration and creates a route and adds it to the RouteCollection.
     *
     * @param string $file   A routing file path
     * @param string $path   A routing file path
     * @param array  $config An array of file contents, each one being an array of route definitions
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When config pattern is not defined for the given route
     */
    protected function createRouteCollection($file, $path, array $config)
    {
        $processor = new Processor();
        $config = $processor->process($this->getConfigTreeBuilder()->buildTree(), array(array('routes' => $config)));

        $collection = new RouteCollection();
        $collection->addResource(new FileResource($path));

        foreach ($config['routes'] as $name => $config) {
            if (isset($config['resource'])) {
                $this->setCurrentDir(dirname($path));
                $collection->addCollection($this->import($config['resource'], $config['type'], false, $file), $config['prefix'], $config['defaults'], $config['requirements'], $config['options'], $config['hostname_pattern']);
            } elseif (!isset($config['pattern'])) {
                throw new \InvalidArgumentException(sprintf('You must define a "pattern" for the "%s" route.', $name));
            } else {
                $route = new Route($config['pattern'], $config['defaults'], $config['requirements'], $config['options'], $config['hostname_pattern']);
                $collection->add($name, $route);
            }
        }

        return $collection;
    }

    protected function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();

        $builder->root('routing')
            ->fixXmlConfig('route')
            ->fixXmlConfig('import', 'routes')
            ->beforeNormalization()
                ->always(function($v) {
                    if (isset($v['import'])) {
                        $v['import']['id'] = '_'.md5(mt_rand());
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('routes')
                ->useAttributeAsKey('id')
                ->prototype('array')
                    ->fixXmlConfig('default')
                    ->fixXmlConfig('requirement')
                    ->fixXmlConfig('option')
                    ->children()
                        ->scalarNode('pattern')->end()
                        ->scalarNode('resource')->end()
                        ->scalarNode('type')->defaultNull()->end()
                        ->scalarNode('prefix')->defaultNull()->end()
                        ->scalarNode('class')->defaultNull()->end()
                        ->scalarNode('hostname_pattern')->defaultValue('')->end()
                        ->arrayNode('defaults')
                            ->useAttributeAsKey('key')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('requirements')
                            ->useAttributeAsKey('key')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('options')
                            ->useAttributeAsKey('key')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $builder;
    }
}
