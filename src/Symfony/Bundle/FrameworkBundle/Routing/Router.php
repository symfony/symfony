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
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * This Router creates the Loader only when the cache is empty.
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
     * Replaces placeholders with service container parameter values in:
     * - the route defaults,
     * - the route requirements,
     * - the route pattern.
     *
     * @param RouteCollection $collection
     */
    private function resolveParameters(RouteCollection $collection)
    {
        foreach ($collection as $route) {
            if ($route instanceof RouteCollection) {
                $this->resolveParameters($route);
            } else {
                foreach ($route->getDefaults() as $name => $value) {
                    $route->setDefault($name, $this->resolveString($value));
                }

                foreach ($route->getRequirements() as $name => $value) {
                     $route->setRequirement($name, $this->resolveString($value));
                }

                $route->setPattern($this->resolveString($route->getPattern()));
            }
        }
    }

    /**
     * Replaces placeholders with the service container parameters in the given string.
     *
     * @param mixed $value The source string which might contain %placeholders%
     *
     * @return mixed A string where the placeholders have been replaced, or the original value if not a string.
     *
     * @throws ParameterNotFoundException When a placeholder does not exist as a container parameter
     * @throws RuntimeException           When a container value is not a string or a numeric value
     */
    private function resolveString($value)
    {
        $container = $this->container;

        if (null === $value || false === $value || true === $value || is_object($value) || is_array($value)) {
            return $value;
        }

        $escapedValue = preg_replace_callback('/%%|%([^%\s]+)%/', function ($match) use ($container, $value) {
            // skip %%
            if (!isset($match[1])) {
                return '%%';
            }

            $key = strtolower($match[1]);

            if (!$container->hasParameter($key)) {
                throw new ParameterNotFoundException($key);
            }

            $resolved = $container->getParameter($key);

            if (is_string($resolved) || is_numeric($resolved)) {
                return (string) $resolved;
            }

            throw new RuntimeException(sprintf(
                'A string value must be composed of strings and/or numbers,' .
                'but found parameter "%s" of type %s inside string value "%s".',
                $key,
                gettype($resolved),
                $value)
            );

        }, $value);

        return str_replace('%%', '%', $escapedValue);
    }
}
