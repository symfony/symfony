<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Loader\Configurator;

use Symphony\Component\DependencyInjection\Argument\IteratorArgument;
use Symphony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symphony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symphony\Component\ExpressionLanguage\Expression;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ContainerConfigurator extends AbstractConfigurator
{
    const FACTORY = 'container';

    private $container;
    private $loader;
    private $instanceof;
    private $path;
    private $file;
    private $anonymousCount = 0;

    public function __construct(ContainerBuilder $container, PhpFileLoader $loader, array &$instanceof, string $path, string $file)
    {
        $this->container = $container;
        $this->loader = $loader;
        $this->instanceof = &$instanceof;
        $this->path = $path;
        $this->file = $file;
    }

    final public function extension(string $namespace, array $config)
    {
        if (!$this->container->hasExtension($namespace)) {
            $extensions = array_filter(array_map(function ($ext) { return $ext->getAlias(); }, $this->container->getExtensions()));
            throw new InvalidArgumentException(sprintf(
                'There is no extension able to load the configuration for "%s" (in %s). Looked for namespace "%s", found %s',
                $namespace,
                $this->file,
                $namespace,
                $extensions ? sprintf('"%s"', implode('", "', $extensions)) : 'none'
            ));
        }

        $this->container->loadFromExtension($namespace, static::processValue($config));
    }

    final public function import(string $resource, string $type = null, bool $ignoreErrors = false)
    {
        $this->loader->setCurrentDir(dirname($this->path));
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
}

/**
 * Creates a service reference.
 */
function ref(string $id): ReferenceConfigurator
{
    return new ReferenceConfigurator($id);
}

/**
 * Creates an inline service.
 */
function inline(string $class = null): InlineServiceConfigurator
{
    return new InlineServiceConfigurator(new Definition($class));
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
function tagged(string $tag): TaggedIteratorArgument
{
    return new TaggedIteratorArgument($tag);
}

/**
 * Creates an expression.
 */
function expr(string $expression): Expression
{
    return new Expression($expression);
}
