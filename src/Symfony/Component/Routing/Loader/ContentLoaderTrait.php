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

use Symfony\Component\Routing\Loader\Configurator\Traits\LocalizedRouteTrait;
use Symfony\Component\Routing\Loader\Configurator\Traits\PrefixTrait;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
trait ContentLoaderTrait
{
    use LocalizedRouteTrait;
    use PrefixTrait;

    private const AVAILABLE_KEYS = [
        'resource', 'type', 'prefix', 'path', 'host', 'schemes', 'methods', 'defaults', 'requirements', 'options', 'condition', 'controller', 'name_prefix', 'trailing_slash_on_root', 'locale', 'format', 'utf8', 'exclude', 'stateless',
    ];

    private function loadContent(RouteCollection $collection, array $config, string $path, string $file): void
    {
        foreach ($config as $name => $config) {
            if (!str_starts_with($when = $name, 'when@')) {
                $config = [$name => $config];
            } elseif (!$this->env || 'when@'.$this->env !== $name) {
                continue;
            } else {
                $when .= '" when "@'.$this->env;
            }

            foreach ($config as $name => $config) {
                $this->validate($config, $when, $path);

                if (isset($config['resource'])) {
                    $this->parseImport($collection, $config, $path, $file);
                } else {
                    $this->parseRoute($collection, $name, $config, $path);
                }
            }
        }
    }

    /**
     * Parses a route and adds it to the RouteCollection.
     */
    private function parseRoute(RouteCollection $collection, string $name, array $config, string $path): void
    {
        if (isset($config['alias'])) {
            $alias = $collection->addAlias($name, $config['alias']);
            $deprecation = $config['deprecated'] ?? null;
            if (null !== $deprecation) {
                $alias->setDeprecated(
                    $deprecation['package'],
                    $deprecation['version'],
                    $deprecation['message'] ?? ''
                );
            }

            return;
        }

        $defaults = $config['defaults'] ?? [];
        $requirements = $config['requirements'] ?? [];
        $options = $config['options'] ?? [];

        foreach ($requirements as $placeholder => $requirement) {
            if (\is_int($placeholder)) {
                throw new \InvalidArgumentException(\sprintf('A placeholder name must be a string (%d given). Did you forget to specify the placeholder key for the requirement "%s" of route "%s" in "%s"?', $placeholder, $requirement, $name, $path));
            }
        }

        if (isset($config['controller'])) {
            $defaults['_controller'] = $config['controller'];
        }
        if (isset($config['locale'])) {
            $defaults['_locale'] = $config['locale'];
        }
        if (isset($config['format'])) {
            $defaults['_format'] = $config['format'];
        }
        if (isset($config['utf8'])) {
            $options['utf8'] = $config['utf8'];
        }
        if (isset($config['stateless'])) {
            $defaults['_stateless'] = $config['stateless'];
        }

        $routes = $this->createLocalizedRoute(new RouteCollection(), $name, $config['path']);
        $routes->addDefaults($defaults);
        $routes->addRequirements($requirements);
        $routes->addOptions($options);
        $routes->setSchemes($config['schemes'] ?? []);
        $routes->setMethods($config['methods'] ?? []);
        $routes->setCondition($config['condition'] ?? null);

        if (isset($config['host'])) {
            $this->addHost($routes, $config['host']);
        }

        $collection->addCollection($routes);
    }

    /**
     * Parses an import and adds the routes in the resource to the RouteCollection.
     */
    private function parseImport(RouteCollection $collection, array $config, string $path, string $file): void
    {
        $type = $config['type'] ?? null;
        $prefix = $config['prefix'] ?? '';
        $defaults = $config['defaults'] ?? [];
        $requirements = $config['requirements'] ?? [];
        $options = $config['options'] ?? [];
        $host = $config['host'] ?? null;
        $condition = $config['condition'] ?? null;
        $schemes = $config['schemes'] ?? null;
        $methods = $config['methods'] ?? null;
        $trailingSlashOnRoot = $config['trailing_slash_on_root'] ?? true;
        $namePrefix = $config['name_prefix'] ?? null;
        $exclude = $config['exclude'] ?? null;

        if (isset($config['controller'])) {
            $defaults['_controller'] = $config['controller'];
        }
        if (isset($config['locale'])) {
            $defaults['_locale'] = $config['locale'];
        }
        if (isset($config['format'])) {
            $defaults['_format'] = $config['format'];
        }
        if (isset($config['utf8'])) {
            $options['utf8'] = $config['utf8'];
        }
        if (isset($config['stateless'])) {
            $defaults['_stateless'] = $config['stateless'];
        }

        $this->setCurrentDir(\dirname($path));

        /** @var RouteCollection[] $imported */
        $imported = $this->import($config['resource'], $type, false, $file, $exclude) ?: [];

        if (!\is_array($imported)) {
            $imported = [$imported];
        }

        foreach ($imported as $subCollection) {
            $this->addPrefix($subCollection, $prefix, $trailingSlashOnRoot);

            if (null !== $host) {
                $this->addHost($subCollection, $host);
            }
            if (null !== $condition) {
                $subCollection->setCondition($condition);
            }
            if (null !== $schemes) {
                $subCollection->setSchemes($schemes);
            }
            if (null !== $methods) {
                $subCollection->setMethods($methods);
            }
            if (null !== $namePrefix) {
                $subCollection->addNamePrefix($namePrefix);
            }
            $subCollection->addDefaults($defaults);
            $subCollection->addRequirements($requirements);
            $subCollection->addOptions($options);

            $collection->addCollection($subCollection);
        }
    }

    /**
     * @throws \InvalidArgumentException If one of the provided config keys is not supported,
     *                                   something is missing or the combination is nonsense
     */
    private function validate(mixed $config, string $name, string $path): void
    {
        if (!\is_array($config)) {
            throw new \InvalidArgumentException(\sprintf('The definition of "%s" in "%s" must be an array.', $name, $path));
        }
        if (isset($config['alias'])) {
            $this->validateAlias($config, $name, $path);

            return;
        }
        if ($extraKeys = array_diff(array_keys($config), self::AVAILABLE_KEYS)) {
            throw new \InvalidArgumentException(\sprintf('The routing file "%s" contains unsupported keys for "%s": "%s". Expected one of: "%s".', $path, $name, implode('", "', $extraKeys), implode('", "', self::AVAILABLE_KEYS)));
        }
        if (isset($config['resource']) && isset($config['path'])) {
            throw new \InvalidArgumentException(\sprintf('The routing file "%s" must not specify both the "resource" key and the "path" key for "%s". Choose between an import and a route definition.', $path, $name));
        }
        if (!isset($config['resource']) && isset($config['type'])) {
            throw new \InvalidArgumentException(\sprintf('The "type" key for the route definition "%s" in "%s" is unsupported. It is only available for imports in combination with the "resource" key.', $name, $path));
        }
        if (!isset($config['resource']) && !isset($config['path'])) {
            throw new \InvalidArgumentException(\sprintf('You must define a "path" for the route "%s" in file "%s".', $name, $path));
        }
        if (isset($config['controller']) && isset($config['defaults']['_controller'])) {
            throw new \InvalidArgumentException(\sprintf('The routing file "%s" must not specify both the "controller" key and the defaults key "_controller" for "%s".', $path, $name));
        }
        if (isset($config['stateless']) && isset($config['defaults']['_stateless'])) {
            throw new \InvalidArgumentException(\sprintf('The routing file "%s" must not specify both the "stateless" key and the defaults key "_stateless" for "%s".', $path, $name));
        }
    }

    /**
     * @throws \InvalidArgumentException If one of the provided config keys is not supported,
     *                                   something is missing or the combination is nonsense
     */
    private function validateAlias(array $config, string $name, string $path): void
    {
        foreach ($config as $key => $value) {
            if (!\in_array($key, ['alias', 'deprecated'], true)) {
                throw new \InvalidArgumentException(\sprintf('The routing file "%s" must not specify other keys than "alias" and "deprecated" for "%s".', $path, $name));
            }

            if ('deprecated' === $key) {
                if (!isset($value['package'])) {
                    throw new \InvalidArgumentException(\sprintf('The routing file "%s" must specify the attribute "package" of the "deprecated" option for "%s".', $path, $name));
                }

                if (!isset($value['version'])) {
                    throw new \InvalidArgumentException(\sprintf('The routing file "%s" must specify the attribute "version" of the "deprecated" option for "%s".', $path, $name));
                }
            }
        }
    }
}
