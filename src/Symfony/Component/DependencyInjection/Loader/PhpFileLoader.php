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
use Symfony\Component\Config\Builder\ConfigClassAwareBuilderGeneratorInterface;
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
use Symfony\Config\Config;

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
    private array $configurations = [];

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

        if ($this->generator instanceof ConfigClassAwareBuilderGeneratorInterface) {
            foreach ($this->container->getExtensions() as $extension) {
                if (!$extension instanceof ConfigurationExtensionInterface) {
                    continue;
                }

                $this->configurations[$extension->getAlias()] ??= $extension->getConfiguration([], $this->container);

                $this->generator->build($this->configurations[$extension->getAlias()])();
            }

            if (!class_exists(Config::class)) {
                $this->generator->buildConfigClassAndTraits($this->configurations)();
            }
        }

        // the closure forbids access to the private scope in the included file
        $config = class_exists(Config::class) ? new Config($this->env) : null;
        $load = \Closure::bind(function ($path, $env) use ($container, $loader, $resource, $type, $config) {
            return include $path;
        }, $this, ProtectedPhpFileLoader::class);

        try {
            $callback = $load($path, $this->env);
            $containerConfigurator = new ContainerConfigurator($this->container, $this, $this->instanceof, $path, $resource, $this->env);

            if (\is_object($callback) && \is_callable($callback)) {
                $this->executeCallback($callback, $containerConfigurator, $path);
            }

            if (null !== $config && $this->generator) {
                $this->processConfigClass($config, $containerConfigurator);
            }
        } finally {
            $this->instanceof = [];
            $this->registerAliasesForSinglyImplementedInterfaces();
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
    private function executeCallback(callable $callback, ContainerConfigurator $containerConfigurator, string $path): void
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
            return;
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

        // Force load ContainerConfigurator to make env(), param() etc available.
        class_exists(ContainerConfigurator::class);

        ++$this->importing;
        try {
            $callback(...$arguments);
        } finally {
            --$this->importing;
        }

        foreach ($configBuilders as $configBuilder) {
            $this->loadExtensionConfig($configBuilder->getExtensionAlias(), ContainerConfigurator::processValue($configBuilder->toArray()));
        }

        $this->loadExtensionConfigs();
    }

    private function processConfigClass(Config $config, ContainerConfigurator $containerConfigurator): void
    {
        foreach ($config->getBuilders() as $configBuilder) {
            $this->configBuilder($configBuilder::class);

            $this->loadExtensionConfig($configBuilder->getExtensionAlias(), ContainerConfigurator::processValue($configBuilder->toArray()));
        }

        foreach ($config->getImports() as $import) {
            if (\is_array($import)) {
                $containerConfigurator->import($import['resource'], $import['type'] ?? null, $import['ignoreErrors'] ?? false);
            } else {
                $containerConfigurator->import($import);
            }
        }

        $parametersConfigurator = $containerConfigurator->parameters();
        foreach ($config->getParameters() as $key => $value) {
            $parametersConfigurator->set($key, $value);
        }

        $servicesConfigurator = $containerConfigurator->services();
        foreach ($config->getServices() as $id => $class) {
            if (!\is_array($class)) {
                $servicesConfigurator->set($id, $class);

                continue;
            }

            $serviceConfigurator = $servicesConfigurator->set($class['id'], $class['class'] ?? null);

            if (isset($class['alias'])) {
                $servicesConfigurator->alias($class['id'], $class['alias']);

                // nothing else to do with aliases
                continue;
            }

            if (isset($class['parent'])) {
                $serviceConfigurator->parent($class['parent']);
            }

            if (isset($class['shared'])) {
                $serviceConfigurator->share($class['shared']);
            }

            if (isset($class['synthetic'])) {
                $serviceConfigurator->synthetic($class['synthetic']);
            }

            if (isset($class['lazy'])) {
                $serviceConfigurator->lazy($class['lazy']);
            }

            if (null !== $public = $class['public'] ?? null) {
                $serviceConfigurator->{$public ? 'public' : 'private'}();
            }

            if (isset($class['abstract'])) {
                $serviceConfigurator->abstract($class['abstract']);
            }

            if (isset($class['deprecated'])) {
                if (\is_array($class['deprecated'])) {
                    $serviceConfigurator->deprecate($class['deprecated']['package'], $class['deprecated']['version'], $class['deprecated']['message'] ?? '');
                } else {
                    $serviceConfigurator->deprecate('', '', $class['deprecated']);
                }
            }

            if (isset($class['factory'])) {
                $serviceConfigurator->factory($class['factory']);
            }

            if (isset($class['constructor'])) {
                $serviceConfigurator->constructor($class['constructor']);
            }

            if (isset($class['file'])) {
                $serviceConfigurator->file($class['file']);
            }

            if (isset($class['arguments'])) {
                $serviceConfigurator->args($class['arguments']);
            }

            foreach ($class['properties'] ?? [] as $property => $value) {
                $serviceConfigurator->property($property, $value);
            }

            if (isset($class['configurator'])) {
                $serviceConfigurator->configurator($class['configurator']);
            }

            foreach ($class['calls'] ?? [] as $call) {
                if (isset($call['method'])) {
                    $serviceConfigurator->call($call['method'], $call['arguments'] ?? [], $call['returns_clone'] ?? false);
                } elseif (isset($call[0])) {
                    $serviceConfigurator->call($call[0], $call[1] ?? [], $call[2] ?? false);
                }
            }

            foreach ($class['tags'] ?? [] as $tag) {
                if (\is_array($tag)) {
                    $name = $tag['name'];
                    unset($tag['name']);
                    $serviceConfigurator->tag($name, $tag);
                } else {
                    $serviceConfigurator->tag($tag);
                }
            }

            if (isset($class['decorates'])) {
                $serviceConfigurator->decorate(
                    $class['decorates'],
                    $class['decoration_inner_name'] ?? null,
                    $class['decoration_priority'] ?? 0,
                    $class['decoration_on_invalid'] ?? 'exception'
                );
            }

            if (isset($class['autowire'])) {
                $serviceConfigurator->autowire($class['autowire']);
            }

            if (isset($class['autoconfigure'])) {
                $serviceConfigurator->autoconfigure($class['autoconfigure']);
            }

            foreach ($class['bind'] ?? [] as $argument => $value) {
                $serviceConfigurator->bind($argument, $value);
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

        $configuration = $this->configurations[$alias] ?? $extension->getConfiguration([], $this->container);
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
