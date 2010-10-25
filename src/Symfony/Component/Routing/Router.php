<?php

namespace Symfony\Component\Routing;

use Symfony\Component\Routing\Loader\LoaderInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * The Router class is an example of the integration of all pieces of the
 * routing system for easier use.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Router implements RouterInterface
{
    protected $matcher;
    protected $generator;
    protected $options;
    protected $defaults;
    protected $context;
    protected $loader;
    protected $collection;
    protected $resource;

    /**
     * Constructor.
     *
     * Available options:
     *
     *   * cache_dir: The cache directory (or null to disable caching)
     *   * debug:     Whether to enable debugging or not (false by default)
     *
     * @param LoaderInterface $loader A LoaderInterface instance
     * @param mixed           $resource The main resource to load
     * @param array           $options  An array of options
     * @param array           $context  The context
     * @param array           $defaults The default values
     *
     * @throws \InvalidArgumentException When unsupported option is provided
     */
    public function __construct(LoaderInterface $loader, $resource, array $options = array(), array $context = array(), array $defaults = array())
    {
        $this->loader = $loader;
        $this->resource = $resource;
        $this->context = $context;
        $this->defaults = $defaults;
        $this->options = array(
            'cache_dir'              => null,
            'debug'                  => false,
            'generator_class'        => 'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            'generator_base_class'   => 'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            'generator_dumper_class' => 'Symfony\\Component\\Routing\\Generator\\Dumper\\PhpGeneratorDumper',
            'generator_cache_class'  => 'ProjectUrlGenerator',
            'matcher_class'          => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'matcher_base_class'     => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'matcher_dumper_class'   => 'Symfony\\Component\\Routing\\Matcher\\Dumper\\PhpMatcherDumper',
            'matcher_cache_class'    => 'ProjectUrlMatcher',
        );

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->loader->load($this->resource);
        }

        return $this->collection;
    }

    /**
     * Sets the request context.
     *
     * @param array $context  The context
     */
    public function setContext(array $context = array())
    {
        $this->context = $context;
    }

    /**
     * Sets the defaults.
     *
     * @param array $defaults The defaults
     */
    public function setDefaults(array $defaults = array())
    {
        $this->defaults = $defaults;
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param  string  $name       The name of the route
     * @param  array   $parameters An array of parameters
     * @param  Boolean $absolute   Whether to generate an absolute URL
     *
     * @return string The generated URL
     */
    public function generate($name, array $parameters = array(), $absolute = false)
    {
        return $this->getGenerator()->generate($name, $parameters, $absolute);
    }

    /**
     * Tries to match a URL with a set of routes.
     *
     * Returns false if no route matches the URL.
     *
     * @param  string $url URL to be parsed
     *
     * @return array|false An array of parameters or false if no route matches
     */
    public function match($url)
    {
        return $this->getMatcher()->match($url);
    }

    /**
     * Gets the UrlMatcher instance associated with this Router.
     *
     * @return UrlMatcherInterface A UrlMatcherInterface instance
     */
    public function getMatcher()
    {
        if (null !== $this->matcher) {
            return $this->matcher;
        }

        if (null === $this->options['cache_dir'] || null === $this->options['matcher_cache_class']) {
            return $this->matcher = new $this->options['matcher_class']($this->getRouteCollection(), $this->context, $this->defaults);
        }

        $class = $this->options['matcher_cache_class'];
        if ($this->needsReload($class)) {
            $dumper = new $this->options['matcher_dumper_class']($this->getRouteCollection());

            $options = array(
                'class'      => $class,
                'base_class' => $this->options['matcher_base_class'],
            );

            $this->updateCache($class, $dumper->dump($options));
        }

        require_once $this->getCacheFile($class);

        return $this->matcher = new $class($this->context, $this->defaults);
    }

    /**
     * Gets the UrlGenerator instance associated with this Router.
     *
     * @return UrlGeneratorInterface A UrlGeneratorInterface instance
     */
    public function getGenerator()
    {
        if (null !== $this->generator) {
            return $this->generator;
        }

        if (null === $this->options['cache_dir'] || null === $this->options['generator_cache_class']) {
            return $this->generator = new $this->options['generator_class']($this->getRouteCollection(), $this->context, $this->defaults);
        }

        $class = $this->options['generator_cache_class'];
        if ($this->needsReload($class)) {
            $dumper = new $this->options['generator_dumper_class']($this->getRouteCollection());

            $options = array(
                'class'      => $class,
                'base_class' => $this->options['generator_base_class'],
            );

            $this->updateCache($class, $dumper->dump($options));
        }

        require_once $this->getCacheFile($class);

        return $this->generator = new $class($this->context, $this->defaults);
    }

    protected function updateCache($class, $dump)
    {
        $this->writeCacheFile($this->getCacheFile($class), $dump);

        if ($this->options['debug']) {
            $this->writeCacheFile($this->getCacheFile($class, 'meta'), serialize($this->getRouteCollection()->getResources()));
        }
    }

    protected function needsReload($class)
    {
        $file = $this->getCacheFile($class);
        if (!file_exists($file)) {
            return true;
        }

        if (!$this->options['debug']) {
            return false;
        }

        $metadata = $this->getCacheFile($class, 'meta');
        if (!file_exists($metadata)) {
            return true;
        }

        $time = filemtime($file);
        $meta = unserialize(file_get_contents($metadata));
        foreach ($meta as $resource) {
            if (!$resource->isUptodate($time)) {
                return true;
            }
        }

        return false;
    }

    protected function getCacheFile($class, $extension = 'php')
    {
        return $this->options['cache_dir'].'/'.$class.'.'.$extension;
    }

    /**
     * @throws \RuntimeException When cache file can't be wrote
     */
    protected function writeCacheFile($file, $content)
    {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $file)) {
            chmod($file, 0644);

            return;
        }

        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }
}
