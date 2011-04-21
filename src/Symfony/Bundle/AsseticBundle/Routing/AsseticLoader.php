<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Routing;

use Assetic\Asset\AssetInterface;
use Assetic\Factory\LazyAssetManager;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads routes for all assets.
 *
 * Assets should only be served through the routing system for ease-of-use
 * during development.
 *
 * For example, add the following to your application's routing_dev.yml:
 *
 *     _assetic:
 *         resource: .
 *         type:     assetic
 *
 * In a production environment you should use the `assetic:dump` command to
 * create static asset files.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class AsseticLoader extends Loader
{
    protected $am;

    public function __construct(LazyAssetManager $am)
    {
        $this->am = $am;
    }

    public function load($routingResource, $type = null)
    {
        $routes = new RouteCollection();

        // resources
        foreach ($this->am->getResources() as $resources) {
            if (!$resources instanceof \Traversable) {
                $resources = array($resources);
            }
            foreach ($resources as $resource) {
                if (file_exists($path = (string) $resource)) {
                    $routes->addResource(new FileResource($path));
                }
            }
        }

        // routes
        foreach ($this->am->getNames() as $name) {
            $asset = $this->am->get($name);
            $formula = $this->am->getFormula($name);

            $this->loadRouteForAsset($routes, $asset, $name);

            // add a route for each "leaf" in debug mode
            if (isset($formula[2]['debug']) ? $formula[2]['debug'] : $this->am->isDebug()) {
                $i = 0;
                foreach ($asset as $leaf) {
                    $this->loadRouteForAsset($routes, $leaf, $name, $i++);
                }
            }
        }

        return $routes;
    }

    /**
     * Loads a route to serve an supplied asset.
     *
     * The fake front controller that {@link UseControllerWorker} adds to the
     * target URL will be removed before set as a route pattern.
     *
     * @param RouteCollection $routes The route collection
     * @param AssetInterface  $asset  The asset
     * @param string          $name   The name to use
     * @param integer         $pos    The leaf index
     */
    private function loadRouteForAsset(RouteCollection $routes, AssetInterface $asset, $name, $pos = null)
    {
        $defaults = array(
            '_controller' => 'assetic.controller:render',
            'name'        => $name,
            'pos'         => $pos,
        );

        // remove the fake front controller
        $pattern = str_replace('_controller/', '', $asset->getTargetUrl());

        if ($format = pathinfo($pattern, PATHINFO_EXTENSION)) {
            $defaults['_format'] = $format;
        }

        $route = '_assetic_'.$name;
        if (null !== $pos) {
            $route .= '_'.$pos;
        }

        $routes->add($route, new Route($pattern, $defaults));
    }

    public function supports($resource, $type = null)
    {
        return 'assetic' == $type;
    }
}
