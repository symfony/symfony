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

use Symfony\Component\Routing\RouterInterface;

/**
 * This Router is optimized to work with matcher and generator classes
 * generated in the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CachedRouter implements RouterInterface
{
    protected $matcher;
    protected $generator;

    /**
     * Constructor.
     *
     * @param string $cacheDir  The cache directory
     * @param string $matcher   The matcher class name
     * @param string $generator The generator class name
     * @param array  $context   The context
     * @param array  $defaults  The default values
     */
    public function __construct($cacheDir, $matcher, $generator, array $context = array(), array $defaults = array())
    {
        if (!file_exists($cache = $cacheDir.'/'.$matcher.'.php')) {
            throw new \RuntimeException(sprintf('The routing cache is not warmed up (%s).', $cache));
        }

        if (!class_exists($matcher, false)) {
            require_once $cache;
            require_once $cacheDir.'/'.$generator.'.php';
        }

        $this->matcher = new $matcher($context, $defaults);
        $this->generator = new $generator($context, $defaults);
    }

    /**
     * Sets the request context.
     *
     * @param array $context  The context
     */
    public function setContext(array $context = array())
    {
        $this->matcher->setContext($context);
        $this->generator->setContext($context);
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
        return $this->generator->generate($name, $parameters, $absolute);
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
        return $this->matcher->match($url);
    }

    /**
     * Gets the UrlGenerator instance associated with this Router.
     *
     * @return UrlGeneratorInterface A UrlGeneratorInterface instance
     */
    public function getGenerator()
    {
        return $this->generator;
    }
}
