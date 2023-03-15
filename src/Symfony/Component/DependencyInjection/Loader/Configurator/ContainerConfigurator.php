<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ContainerConfigurator extends AbstractConfigurator
{
    public const FACTORY = 'container';

    private ContainerBuilder $container;
    private PhpFileLoader $loader;
    private array $instanceof;
    private string $path;
    private string $file;
    private int $anonymousCount = 0;
    private ?string $env;

    public function __construct(ContainerBuilder $container, PhpFileLoader $loader, array &$instanceof, string $path, string $file, string $env = null)
    {
        $this->container = $container;
        $this->loader = $loader;
        $this->instanceof = &$instanceof;
        $this->path = $path;
        $this->file = $file;
        $this->env = $env;
    }

    final public function extension(string $namespace, array $config): void
    {
        if (!$this->container->hasExtension($namespace)) {
            $extensions = array_filter(array_map(fn (ExtensionInterface $ext) => $ext->getAlias(), $this->container->getExtensions()));
            throw new InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in "%s"). Looked for namespace "%s", found "%s".', $namespace, $this->file, $namespace, $extensions ? implode('", "', $extensions) : 'none'));
        }

        $this->container->loadFromExtension($namespace, static::processValue($config));
    }

    final public function import(string $resource, string $type = null, bool|string $ignoreErrors = false): void
    {
        $this->loader->setCurrentDir(\dirname($this->path));
        $this->loader->import($resource, $type, $ignoreErrors, $this->file);
    }

    final public function parameters(): ParametersConfigurator
    {
        return new ParametersConfigurator($this->container);
    }

    final public function services(): ServicesConfigurator
    {
        return new ServicesConfigurator($this->container, $this->loader, $this->instanceof, $this->path, $this->anonymousCount);
    }

    /**
     * Get the current environment to be able to write conditional configuration.
     */
    final public function env(): ?string
    {
        return $this->env;
    }

    final public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $clone->file = $path;
        $clone->loader->setCurrentDir(\dirname($path));

        return $clone;
    }
}

/**
 * Creates a parameter.
 */
function param(string $name): ParamConfigurator
{
    return new ParamConfigurator($name);
}

/**
 * Creates a reference to a service.
 */
function service(string $serviceId): ReferenceConfigurator
{
    return new ReferenceConfigurator($serviceId);
}

/**
 * Creates an inline service.
 */
function inline_service(string $class = null): InlineServiceConfigurator
{
    return new InlineServiceConfigurator(new Definition($class));
}

/**
 * Creates a service locator.
 *
 * @param ReferenceConfigurator[] $values
 */
function service_locator(array $values): ServiceLocatorArgument
{
    $values = AbstractConfigurator::processValue($values, true);

    if (isset($values[0])) {
        trigger_deprecation('symfony/dependency-injection', '6.3', 'Using integers as keys in a "service_locator()" argument is deprecated. The keys will default to the IDs of the original services in 7.0.');
    }

    return new ServiceLocatorArgument($values);
}

/**
 * Creates a lazy iterator.
 *
 * @param ReferenceConfigurator[] $values
 */
function iterator(array $values): IteratorArgument
{
    return new IteratorArgument(AbstractConfigurator::processValue($values, true));
}

/**
 * Creates a lazy iterator by tag name.
 */
function tagged_iterator(string $tag, string $indexAttribute = null, string $defaultIndexMethod = null, string $defaultPriorityMethod = null, string|array $exclude = []): TaggedIteratorArgument
{
    return new TaggedIteratorArgument($tag, $indexAttribute, $defaultIndexMethod, false, $defaultPriorityMethod, (array) $exclude);
}

/**
 * Creates a service locator by tag name.
 */
function tagged_locator(string $tag, string $indexAttribute = null, string $defaultIndexMethod = null, string $defaultPriorityMethod = null, string|array $exclude = []): ServiceLocatorArgument
{
    return new ServiceLocatorArgument(new TaggedIteratorArgument($tag, $indexAttribute, $defaultIndexMethod, true, $defaultPriorityMethod, (array) $exclude));
}

/**
 * Creates an expression.
 */
function expr(string $expression): Expression
{
    return new Expression($expression);
}

/**
 * Creates an abstract argument.
 */
function abstract_arg(string $description): AbstractArgument
{
    return new AbstractArgument($description);
}

/**
 * Creates an environment variable reference.
 */
function env(string $name): EnvConfigurator
{
    return new EnvConfigurator($name);
}

/**
 * Creates a closure service reference.
 */
function service_closure(string $serviceId): ClosureReferenceConfigurator
{
    return new ClosureReferenceConfigurator($serviceId);
}

/**
 * Creates a closure.
 */
function closure(string|array|ReferenceConfigurator|Expression $callable): InlineServiceConfigurator
{
    return (new InlineServiceConfigurator(new Definition('Closure')))
        ->factory(['Closure', 'fromCallable'])
        ->args([$callable]);
}
