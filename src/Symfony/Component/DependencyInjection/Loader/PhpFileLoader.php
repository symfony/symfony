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
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\App;
use Symfony\Component\DependencyInjection\Loader\Configurator\AppReference;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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

    private ?ConfigBuilderGeneratorInterface $generator;

    public function __construct(
        ContainerBuilder $container,
        FileLocatorInterface $locator,
        ?string $env = null,
        ConfigBuilderGeneratorInterface|bool|null $generator = null,
        bool $prepend = false,
    ) {
        if (\is_bool($generator)) {
            $prepend = $generator;
            $generator = null;
        }
        $this->generator = $generator;

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

        // Force load ContainerConfigurator to make env(), param() etc available.
        class_exists(ContainerConfigurator::class);

        // Expose AppReference::config() as App::config()
        if (!class_exists(App::class)) {
            class_alias(AppReference::class, App::class);
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
                $this->callConfigurator($result, new ContainerConfigurator($this->container, $this, $this->instanceof, $path, $resource, $this->env), $path);
            } elseif (\is_array($result)) {
                $yamlLoader = new YamlFileLoader($this->container, $this->locator, $this->env, $this->prepend);
                $yamlLoader->setResolver($this->resolver ?? new LoaderResolver([$this]));
                $loadContent = new \ReflectionMethod(YamlFileLoader::class, 'loadContent');
                $result = ContainerConfigurator::processValue($result);

                ++$this->importing;
                try {
                    $content = array_intersect_key($result, ['imports' => true, 'parameters' => true, 'services' => true]);
                    $loadContent->invoke($yamlLoader, $content, $path);

                    foreach ($result as $namespace => $config) {
                        if (\in_array($namespace, ['imports', 'parameters', 'services'], true)) {
                            continue;
                        }
                        if (str_starts_with($namespace, 'when@')) {
                            $knownEnvs = $this->container->hasParameter('.container.known_envs') ? array_flip($this->container->getParameter('.container.known_envs')) : [];
                            $this->container->setParameter('.container.known_envs', array_keys($knownEnvs + [substr($namespace, 5) => true]));
                            continue;
                        }
                        $this->loadExtensionConfig($namespace, $config);
                    }

                    // per-env configuration
                    if ($this->env && isset($result[$when = 'when@'.$this->env])) {
                        if (!\is_array($result[$when])) {
                            throw new InvalidArgumentException(\sprintf('The "%s" key should contain an array in "%s".', $when, $path));
                        }

                        $content = array_intersect_key($result[$when], ['imports' => true, 'parameters' => true, 'services' => true]);
                        $loadContent->invoke($yamlLoader, $content, $path);

                        foreach ($result[$when] as $namespace => $config) {
                            if (!\in_array($namespace, ['imports', 'parameters', 'services'], true) && !str_starts_with($namespace, 'when@')) {
                                $this->loadExtensionConfig($namespace, $config);
                            }
                        }
                    }
                } finally {
                    --$this->importing;
                }
            }

            $this->loadExtensionConfigs();
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
    private function callConfigurator(callable $callback, ContainerConfigurator $containerConfigurator, string $path): void
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
                    trigger_deprecation('symfony/dependency-injection', '7.4', 'Using fluent builders for semantic configuration is deprecated, instantiate the "%s" class with the config array as argument and return it instead in "%s".', $type, $path);
                    $configBuilders[] = $configBuilder;
                    $arguments[] = $configBuilder;
            }
        }

        ++$this->importing;
        try {
            $callback(...$arguments);
        } finally {
            --$this->importing;
        }

        foreach ($configBuilders as $configBuilder) {
            $this->loadExtensionConfig($configBuilder->getExtensionAlias(), ContainerConfigurator::processValue($configBuilder->toArray()));
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
