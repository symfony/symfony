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

use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\Config\Exception\FileLoaderLoadException;
use Symfony\Component\Config\Loader\DelegatingLoader as BaseDelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolverInterface;

/**
 * DelegatingLoader delegates route loading to other loaders using a loader resolver.
 *
 * This implementation resolves the _controller attribute from the short notation
 * to the fully-qualified form (from a:b:c to class::method).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DelegatingLoader extends BaseDelegatingLoader
{
    protected $parser;
    private $loading = false;

    /**
     * @param ControllerNameParser    $parser   A ControllerNameParser instance
     * @param LoaderResolverInterface $resolver A LoaderResolverInterface instance
     */
    public function __construct(ControllerNameParser $parser, LoaderResolverInterface $resolver)
    {
        $this->parser = $parser;

        parent::__construct($resolver);
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        if ($this->loading) {
            // This can happen if a fatal error occurs in parent::load().
            // Here is the scenario:
            // - while routes are being loaded by parent::load() below, a fatal error
            //   occurs (e.g. parse error in a controller while loading annotations);
            // - PHP abruptly empties the stack trace, bypassing all catch/finally blocks;
            //   it then calls the registered shutdown functions;
            // - the ErrorHandler catches the fatal error and re-injects it for rendering
            //   thanks to HttpKernel->terminateWithException() (that calls handleException());
            // - at this stage, if we try to load the routes again, we must prevent
            //   the fatal error from occurring a second time,
            //   otherwise the PHP process would be killed immediately;
            // - while rendering the exception page, the router can be required
            //   (by e.g. the web profiler that needs to generate an URL);
            // - this handles the case and prevents the second fatal error
            //   by triggering an exception beforehand.

            throw new FileLoaderLoadException($resource, null, null, null, $type);
        }
        $this->loading = true;

        try {
            $collection = parent::load($resource, $type);
        } finally {
            $this->loading = false;
        }

        foreach ($collection->all() as $route) {
            if (!\is_string($controller = $route->getDefault('_controller')) || !$controller) {
                continue;
            }

            try {
                $controller = $this->parser->parse($controller);
            } catch (\InvalidArgumentException $e) {
                // unable to optimize unknown notation
            }

            $route->setDefault('_controller', $controller);
        }

        return $collection;
    }
}
