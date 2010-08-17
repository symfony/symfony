<?php

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameConverter;
use Symfony\Components\Routing\Loader\DelegatingLoader as BaseDelegatingLoader;
use Symfony\Components\Routing\Loader\LoaderResolverInterface;
use Symfony\Components\HttpKernel\Log\LoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DelegatingLoader delegates route loading to other loaders using a loader resolver.
 *
 * This implementation resolves the _controller attribute from the short notation
 * to the fully-qualified form (from a:b:c to class:method).
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DelegatingLoader extends BaseDelegatingLoader
{
    protected $converter;
    protected $logger;

    /**
     * Constructor.
     *
     * @param ControllerNameConverter $converter A ControllerNameConverter instance
     * @param LoggerInterface         $logger    A LoggerInterface instance
     * @param LoaderResolverInterface $resolver  A LoaderResolverInterface instance
     */
    public function __construct(ControllerNameConverter $converter, LoggerInterface $logger = null, LoaderResolverInterface $resolver)
    {
        $this->converter = $converter;
        $this->logger = $logger;

        parent::__construct($resolver);
    }

    /**
     * Loads a resource.
     *
     * @param  mixed $resource A resource
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function load($resource)
    {
        $collection = parent::load($resource);

        foreach ($collection->getRoutes() as $name => $route) {
            if ($controller = $route->getDefault('_controller')) {
                try {
                    $controller = $this->converter->fromShortNotation($controller);
                } catch (\Exception $e) {
                    throw new \RuntimeException(sprintf('%s (for route "%s" in resource "%s")', $e->getMessage(), $name, is_string($resource) ? $resource : 'RESOURCE'), $e->getCode(), $e);
                }

                $route->setDefault('_controller', $controller);
            }
        }

        return $collection;
    }
}
