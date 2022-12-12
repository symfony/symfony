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

use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\DelegatingLoader as BaseDelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * DelegatingLoader delegates route loading to other loaders using a loader resolver.
 *
 * This implementation resolves the _controller attribute from the short notation
 * to the fully-qualified form (from a:b:c to class::method).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class DelegatingLoader extends BaseDelegatingLoader
{
    private bool $loading = false;
    private array $defaultOptions;
    private array $defaultRequirements;

    public function __construct(LoaderResolverInterface $resolver, array $defaultOptions = [], array $defaultRequirements = [])
    {
        $this->defaultOptions = $defaultOptions;
        $this->defaultRequirements = $defaultRequirements;

        parent::__construct($resolver);
    }

    public function load(mixed $resource, string $type = null): RouteCollection
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
            //   (by e.g. the web profiler that needs to generate a URL);
            // - this handles the case and prevents the second fatal error
            //   by triggering an exception beforehand.

            throw new LoaderLoadException($resource, null, 0, null, $type);
        }
        $this->loading = true;

        try {
            $collection = parent::load($resource, $type);
        } finally {
            $this->loading = false;
        }

        foreach ($collection->all() as $route) {
            if ($this->defaultOptions) {
                $route->setOptions($route->getOptions() + $this->defaultOptions);
            }
            if ($this->defaultRequirements) {
                $route->setRequirements($route->getRequirements() + $this->defaultRequirements);
            }
            if (!\is_string($controller = $route->getDefault('_controller'))) {
                continue;
            }

            if (str_contains($controller, '::')) {
                continue;
            }

            $route->setDefault('_controller', $controller);
        }

        return $collection;
    }
}
