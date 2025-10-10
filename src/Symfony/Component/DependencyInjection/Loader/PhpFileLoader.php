<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\Builder\ConfigBuilderGeneratorInterface;
use Symfony\Component\Config\Builder\ConfigBuilderInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Config\ServicesConfig;

/**
 * PhpFileLoader loads service definitions from a PHP file.
 *
 * The PHP file is required and the $container variable can be
 * used within the file to change the container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PhpFileLoader extends FileLoader
{
    protected bool $autoRegisterAliasesForSinglyImplementedInterfaces = false;
    private ?\Closure $configBuilderAutoloader = null;

    public function __construct(
        ContainerBuilder $container,
        FileLocatorInterface $locator,
        ?string $env = null,
        private ?ConfigBuilderGeneratorInterface $generator = null,
        bool $prepend = false,
    ) {
        parent::__construct($container, $locator, $env, $prepend);
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        // the container and loader variables are exposed to the included file below
        $container = $this->container;
        $loader = $this;

        $path = $this->locator->locate($resource);
        $this->setCurrentDir(\dirname($path));
        $this->container->fileExists($path);

        // Ensure symbols in the \Symfony\Config and Configurator namespaces are available
        require_once __DIR__.\DIRECTORY_SEPARATOR.'Config'.\DIRECTORY_SEPARATOR.'functions.php';
        require_once __DIR__.\DIRECTORY_SEPARATOR.'Configurator'.\DIRECTORY_SEPARATOR.'functions.php';

        if ($autoloaderRegistered = !$this->configBuilderAutoloader && $this->generator) {
            spl_autoload_register($this->configBuilderAutoloader = function (string $class) {
                if (str_starts_with($class, 'Symfony\\Config\\') && str_ends_with($class, 'Config')) {
                    $this->configBuilder($class);
                }
            });
        }

        // the closure forbids access to the private scope in the included file
        $load = \Closure::bind(static function ($path, $env) use ($container, $loader, $resource, $type) {
            return include $path;
        }, null, null);

        try {
            try {
                if (1 === $result = $load($path, $this->env)) {
                    $result = null;
                }
            } catch (\Error $e) {
                $load = \Closure::bind(function ($path, $env) use ($container, $loader, $resource, $type) {
                    return include $path;
                }, $this, ProtectedPhpFileLoader::class);

                if (1 === $result = $load($path, $this->env)) {
                    $result = null;
                }

                trigger_deprecation('symfony/dependency-injection', '7.4', 'Using `$this` or its internal scope in config files is deprecated, use the `$loader` variable instead in "%s" on line %d.', $e->getFile(), $e->getLine());
            }

            if (\is_object($result) && \is_callable($result)) {
                $result = $this->callConfigurator($result, new ContainerConfigurator($this->container, $this, $this->instanceof, $path, $resource, $this->env), $path);
            }
            if ($result instanceof ConfigBuilderInterface || $result instanceof ServicesConfig) {
                $result = [$result];
            } elseif (!is_iterable($result ?? [])) {
                throw new InvalidArgumentException(\sprintf('The return value in config file "%s" is invalid: "%s" given.', $path, get_debug_type($result)));
            }

            foreach ($result ?? [] as $key => $config) {
                if (!str_starts_with($key, 'when@')) {
                    $config = [$key => $config];
                } elseif (!$this->env || 'when@'.$this->env !== $key) {
                    continue;
                } elseif ($config instanceof ServicesConfig || $config instanceof ConfigBuilderInterface) {
                    $config = [$config];
                } elseif (!is_iterable($config)) {
                    throw new InvalidArgumentException(\sprintf('The "%s" key should contain an array in "%s".', $key, $path));
                }

                foreach ($config as $key => $config) {
                    if ($config instanceof ServicesConfig || \in_array($key, ['imports', 'parameters', 'services'], true)) {
                        if (!$config instanceof ServicesConfig) {
                            $config = [$key => $config];
                        } elseif (\is_string($key) && 'services' !== $key) {
                            throw new InvalidArgumentException(\sprintf('Invalid key "%s" returned for the "%s" config builder; none or "services" expected in file "%s".', $key, get_debug_type($config), $path));
                        }
                        $yamlLoader = new YamlFileLoader($this->container, $this->locator, $this->env, $this->prepend);
                        $loadContent = new \ReflectionMethod(YamlFileLoader::class, 'loadContent');
                        $loadContent->invoke($yamlLoader, ContainerConfigurator::processValue((array) $config), $path);
                    } elseif ($config instanceof ConfigBuilderInterface) {
                        if (\is_string($key) && $config->getExtensionAlias() !== $key) {
                            throw new InvalidArgumentException(\sprintf('The extension alias "%s" of the "%s" config builder does not match the key "%s" in file "%s".', $config->getExtensionAlias(), get_debug_type($config), $key, $path));
                        }
                        $this->loadExtensionConfig($config->getExtensionAlias(), ContainerConfigurator::processValue($config->toArray()), $path);
                    } elseif (!\is_string($key) || !\is_array($config)) {
                        throw new InvalidArgumentException(\sprintf('The configuration returned in file "%s" must yield only string-keyed arrays or ConfigBuilderInterface objects.', $path));
                    } else {
                        if (str_starts_with($key, 'when@')) {
                            throw new InvalidArgumentException(\sprintf('A service name cannot start with "when@" in "%s".', $path));
                        }

                        $this->loadExtensionConfig($key, ContainerConfigurator::processValue($config), $path);
                    }
                }
            }

            $this->loadExtensionConfigs();
        } finally {
            $this->instanceof = [];
            $this->registerAliasesForSinglyImplementedInterfaces();

            if ($autoloaderRegistered) {
                spl_autoload_unregister($this->configBuilderAutoloader);
                $this->configBuilderAutoloader = null;
            }
        }

        return null;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }

        if (null === $type && 'php' === pathinfo($resource, \PATHINFO_EXTENSION)) {
            return true;
        }

        return 'php' === $type;
    }

    /**
     * Resolve the parameters to the $callback and execute it.
     */
    private function callConfigurator(callable $callback, ContainerConfigurator $containerConfigurator, string $path): mixed
    {
        $callback = $callback(...);
        $arguments = [];
        $configBuilders = [];
        $r = new \ReflectionFunction($callback);

        $excluded = true;
        $whenAttributes = $r->getAttributes(When::class, \ReflectionAttribute::IS_INSTANCEOF);
        $notWhenAttributes = $r->getAttributes(WhenNot::class, \ReflectionAttribute::IS_INSTANCEOF);

        if ($whenAttributes && $notWhenAttributes) {
            throw new LogicException('Using both #[When] and #[WhenNot] attributes on the same target is not allowed.');
        }

        if (!$whenAttributes && !$notWhenAttributes) {
            $excluded = false;
        }

        foreach ($whenAttributes as $attribute) {
            if ($this->env === $attribute->newInstance()->env) {
                $excluded = false;
                break;
            }
        }

        foreach ($notWhenAttributes as $attribute) {
            if ($excluded = $this->env === $attribute->newInstance()->env) {
                break;
            }
        }

        if ($excluded) {
            return null;
        }

        foreach ($r->getParameters() as $parameter) {
            $reflectionType = $parameter->getType();
            if (!$reflectionType instanceof \ReflectionNamedType) {
                throw new \InvalidArgumentException(\sprintf('Could not resolve argument "$%s" for "%s". You must typehint it (for example with "%s" or "%s").', $parameter->getName(), $path, ContainerConfigurator::class, ContainerBuilder::class));
            }
            $type = $reflectionType->getName();

            switch ($type) {
                case ContainerConfigurator::class:
                    $arguments[] = $containerConfigurator;
                    break;
                case ContainerBuilder::class:
                    $arguments[] = $this->container;
                    break;
                case FileLoader::class:
                case self::class:
                    $arguments[] = $this;
                    break;
                case 'string':
                    if (null !== $this->env && 'env' === $parameter->getName()) {
                        $arguments[] = $this->env;
                        break;
                    }
                    // no break
                default:
                    try {
                        $configBuilder = $this->configBuilder($type);
                    } catch (InvalidArgumentException|\LogicException $e) {
                        throw new \InvalidArgumentException(\sprintf('Could not resolve argument "%s" for "%s".', $type.' $'.$parameter->getName(), $path), 0, $e);
                    }
                    $configBuilders[] = $configBuilder;
                    $arguments[] = $configBuilder;
            }
        }

        ++$this->importing;
        try {
            $result = $callback(...$arguments);

            return \in_array($result, $configBuilders, true) ? null : $result;
        } catch (\Throwable $e) {
            $configBuilders = [];
            throw $e;
        } finally {
            --$this->importing;

            foreach ($configBuilders as $configBuilder) {
                $this->loadExtensionConfig($configBuilder->getExtensionAlias(), ContainerConfigurator::processValue($configBuilder->toArray()));
            }
        }
    }

    /**
     * @param string $namespace FQCN string for a class implementing ConfigBuilderInterface
     */
    private function configBuilder(string $namespace): ConfigBuilderInterface
    {
        if (!class_exists(ConfigBuilderGenerator::class)) {
            throw new \LogicException('You cannot use the config builder as the Config component is not installed. Try running "composer require symfony/config".');
        }

        if (null === $this->generator) {
            throw new \LogicException('You cannot use the ConfigBuilders without providing a class implementing ConfigBuilderGeneratorInterface.');
        }

        // If class exists and implements ConfigBuilderInterface
        if (class_exists($namespace) && is_subclass_of($namespace, ConfigBuilderInterface::class)) {
            return new $namespace();
        }

        // If it does not start with Symfony\Config\ we don't know how to handle this
        if (!str_starts_with($namespace, 'Symfony\\Config\\')) {
            throw new InvalidArgumentException(\sprintf('Could not find or generate class "%s".', $namespace));
        }

        if (is_a($namespace, ServicesConfig::class, true)) {
            throw new \LogicException(\sprintf('You cannot use "%s" as a config builder; create an instance and return it instead.', $namespace));
        }

        // Try to get the extension alias
        $alias = Container::underscore(substr($namespace, 15, -6));

        if (str_contains($alias, '\\')) {
            throw new InvalidArgumentException('You can only use "root" ConfigBuilders from "Symfony\\Config\\" namespace. Nested classes like "Symfony\\Config\\Framework\\CacheConfig" cannot be used.');
        }

        if (!$this->container->hasExtension($alias)) {
            $extensions = array_filter(array_map(fn (ExtensionInterface $ext) => $ext->getAlias(), $this->container->getExtensions()));
            throw new InvalidArgumentException(UndefinedExtensionHandler::getErrorMessage($namespace, null, $alias, $extensions));
        }

        $extension = $this->container->getExtension($alias);
        if (!$extension instanceof ConfigurationExtensionInterface) {
            throw new \LogicException(\sprintf('You cannot use the config builder for "%s" because the extension does not implement "%s".', $namespace, ConfigurationExtensionInterface::class));
        }

        $configuration = $extension->getConfiguration([], $this->container);
        $loader = $this->generator->build($configuration);

        return $loader();
    }
}

/**
 * @internal
 */
final class ProtectedPhpFileLoader extends PhpFileLoader
{
}
