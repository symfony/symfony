<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Kernel;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurableExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A base class for DI-powered bundles with built-in configuration hooks.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractBundle implements BundleInterface, ConfigurableExtensionInterface
{
    protected string $name;
    protected ExtensionInterface|false|null $extension = null;
    protected string $path;
    protected ?ContainerInterface $container;
    protected string $extensionAlias = '';

    public function boot(): void
    {
    }

    public function shutdown(): void
    {
    }

    /**
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     */
    public function build(ContainerBuilder $container): void
    {
    }

    public function configure(DefinitionConfigurator $definition): void
    {
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ('' === $this->extensionAlias) {
            $this->extensionAlias = Container::underscore(preg_replace('/Bundle$/', '', $this->getName()));
        }

        return $this->extension ??= new BundleExtension($this, $this->extensionAlias);
    }

    public function getPath(): string
    {
        // assume the modern directory structure by default
        return $this->path ??= \dirname((new \ReflectionClass($this))->getFileName(), 2);
    }

    /**
     * Returns the bundle name (the class short name).
     */
    final public function getName(): string
    {
        return $this->name ??= false === ($pos = strrpos(static::class, '\\')) ? static::class : substr(static::class, $pos + 1);
    }

    public function setContainer(?ContainerInterface $container): void
    {
        $this->container = $container;
    }
}
