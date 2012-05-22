<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Router as BaseRouter;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;

/**
 * This Router only creates the Loader only when the cache is empty.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Router extends BaseRouter implements WarmableInterface
{
    private $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param mixed              $resource  The main resource to load
     * @param array              $options   An array of options
     * @param RequestContext     $context   The context
     */
    public function __construct(ContainerInterface $container, $resource, array $options = array(), RequestContext $context = null)
    {
        $this->container = $container;

        $this->resource = $resource;
        $this->context = null === $context ? new RequestContext() : $context;
        $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->container->get('routing.loader')->load($this->resource, $this->options['resource_type']);
            $this->resolveParameters($this->collection);
        }

        return $this->collection;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        $currentDir = $this->getOption('cache_dir');

        // force cache generation
        $this->setOption('cache_dir', $cacheDir);
        $this->getMatcher();
        $this->getGenerator();

        $this->setOption('cache_dir', $currentDir);
    }

    /**
     * Replaces placeholders with service container parameter values in route defaults and requirements.
     *
     * @param $collection
     */
    private function resolveParameters(RouteCollection $collection)
    {
        foreach ($collection as $route) {
            if ($route instanceof RouteCollection) {
                $this->resolveParameters($route);
            } else {
                foreach ($route->getDefaults() as $name => $value) {
                    if (!$value || '%' !== $value[0] || '%' !== substr($value, -1)) {
                        continue;
                    }

                    $key = substr($value, 1, -1);
                    if ($this->container->hasParameter($key)) {
                        $route->setDefault($name, $this->container->getParameter($key));
                    }
                }

                foreach ($route->getRequirements() as $name => $value) {
                    if (!$value || '%' !== $value[0] || '%' !== substr($value, -1)) {
                        continue;
                    }

                    $key = substr($value, 1, -1);
                    if ($this->container->hasParameter($key)) {
                        $route->setRequirement($name, $this->container->getParameter($key));
                    }
                }
            }
        }
    }
}
