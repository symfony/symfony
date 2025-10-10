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
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Loader\Configurator\AliasConfigurator;
use Symfony\Component\Routing\Loader\Configurator\CollectionConfigurator;
use Symfony\Component\Routing\Loader\Configurator\ImportConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Config\RoutesConfig;

/**
 * PhpFileLoader loads routes from a PHP file.
 *
 * The file must return a RouteCollection instance.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas grekas <p@tchwork.com>
 * @author Jules Pietri <jules@heahprod.com>
 */
class PhpFileLoader extends FileLoader
{
    /**
     * Loads a PHP file.
     */
    public function load(mixed $file, ?string $type = null): RouteCollection
    {
        $path = $this->locator->locate($file);
        $this->setCurrentDir(\dirname($path));

        // the closure forbids access to the private scope in the included file
        $loader = $this;
        $load = \Closure::bind(static function ($file) use ($loader) {
            return include $file;
        }, null, null);

        try {
            if (1 === $result = $load($path)) {
                $result = null;
            }
        } catch (\Error $e) {
            $load = \Closure::bind(static function ($file) use ($loader) {
                return include $file;
            }, null, ProtectedPhpFileLoader::class);

            if (1 === $result = $load($path)) {
                $result = null;
            }

            trigger_deprecation('symfony/routing', '7.4', 'Accessing the internal scope of the loader in config files is deprecated, use only its public API instead in "%s" on line %d.', $e->getFile(), $e->getLine());
        }

        if (\is_object($result) && \is_callable($result)) {
            $collection = $this->callConfigurator($result, $path, $file);
        } else {
            $collection = new RouteCollection();
            $this->loadRoutes($collection, $result, $path, $file);
        }

        $collection->addResource(new FileResource($path));

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return \is_string($resource) && 'php' === pathinfo($resource, \PATHINFO_EXTENSION) && (!$type || 'php' === $type);
    }

    protected function callConfigurator(callable $callback, string $path, string $file): RouteCollection
    {
        $collection = new RouteCollection();

        $result = $callback(new RoutingConfigurator($collection, $this, $path, $file, $this->env));
        $this->loadRoutes($collection, $result, $path, $file);

        return $collection;
    }

    private function loadRoutes(RouteCollection $collection, mixed $routes, string $path, string $file): void
    {
        if (null === $routes
            || $routes instanceof RouteCollection
            || $routes instanceof AliasConfigurator
            || $routes instanceof CollectionConfigurator
            || $routes instanceof ImportConfigurator
            || $routes instanceof RouteConfigurator
            || $routes instanceof RoutingConfigurator
        ) {
            if ($routes instanceof RouteCollection && $collection !== $routes) {
                $collection->addCollection($routes);
            }

            return;
        }

        if ($routes instanceof RoutesConfig) {
            $routes = [$routes];
        } elseif (!is_iterable($routes)) {
            throw new InvalidArgumentException(\sprintf('The return value in config file "%s" is invalid: "%s" given.', $path, get_debug_type($routes)));
        }

        $loader = new YamlFileLoader($this->locator, $this->env);

        \Closure::bind(function () use ($collection, $routes, $path, $file) {
            foreach ($routes as $name => $config) {
                $when = $name;
                if (str_starts_with($name, 'when@')) {
                    if (!$this->env || 'when@'.$this->env !== $name) {
                        continue;
                    }
                    $when .= '" when "@'.$this->env;
                } elseif (!$config instanceof RoutesConfig) {
                    $config = [$name => $config];
                } elseif (!\is_int($name)) {
                    throw new InvalidArgumentException(\sprintf('Invalid key "%s" returned for the "%s" config builder; none or "when@%%env%%" expected in file "%s".', $name, get_debug_type($config), $path));
                }

                if ($config instanceof RoutesConfig) {
                    $config = $config->routes;
                } elseif (!is_iterable($config)) {
                    throw new InvalidArgumentException(\sprintf('The "%s" key should contain an array in "%s".', $name, $path));
                }

                foreach ($config as $name => $config) {
                    if (str_starts_with($name, 'when@')) {
                        throw new InvalidArgumentException(\sprintf('A route name cannot start with "when@" in "%s".', $path));
                    }
                    $this->validate($config, $when, $path);

                    if (isset($config['resource'])) {
                        $this->parseImport($collection, $config, $path, $file);
                    } else {
                        $this->parseRoute($collection, $name, $config, $path);
                    }
                }
            }
        }, $loader, $loader::class)();
    }
}

/**
 * @internal
 */
final class ProtectedPhpFileLoader extends PhpFileLoader
{
}
