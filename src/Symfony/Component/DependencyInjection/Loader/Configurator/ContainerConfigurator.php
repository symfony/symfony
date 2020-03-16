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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\ExpressionLanguage\Expression;

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

    public function __construct(ContainerBuilder $container, PhpFileLoader $loader, array &$instanceof, $path, $file)
    {
        $this->container = $container;
        $this->loader = $loader;
        $this->instanceof = &$instanceof;
        $this->path = $path;
        $this->file = $file;
    }

    final public function extension($namespace, array $config)
    {
        if (!$this->container->hasExtension($namespace)) {
            $extensions = array_filter(array_map(function ($ext) { return $ext->getAlias(); }, $this->container->getExtensions()));
            throw new InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in "%s"). Looked for namespace "%s", found "%s".', $namespace, $this->file, $namespace, $extensions ? implode('", "', $extensions) : 'none'));
        }

        $this->container->loadFromExtension($namespace, static::processValue($config));
    }

    final public function import($resource, $type = null, $ignoreErrors = false)
    {
        $this->loader->setCurrentDir(\dirname($this->path));
        $this->loader->import($resource, $type, $ignoreErrors, $this->file);
    }

    /**
     * @return ParametersConfigurator
     */
    final public function parameters()
    {
        return new ParametersConfigurator($this->container);
    }

    /**
     * @return ServicesConfigurator
     */
    final public function services()
    {
        return new ServicesConfigurator($this->container, $this->loader, $this->instanceof);
    }
}

/**
 * Creates a service reference.
 *
 * @param string $id
 *
 * @return ReferenceConfigurator
 */
function ref($id)
{
    return new ReferenceConfigurator($id);
}

/**
 * Creates an inline service.
 *
 * @param string|null $class
 *
 * @return InlineServiceConfigurator
 */
function inline($class = null)
{
    return new InlineServiceConfigurator(new Definition($class));
}

/**
 * Creates a lazy iterator.
 *
 * @param ReferenceConfigurator[] $values
 *
 * @return IteratorArgument
 */
function iterator(array $values)
{
    return new IteratorArgument(AbstractConfigurator::processValue($values, true));
}

/**
 * Creates a lazy iterator by tag name.
 *
 * @param string $tag
 *
 * @return TaggedIteratorArgument
 */
function tagged($tag)
{
    return new TaggedIteratorArgument($tag);
}

/**
 * Creates an expression.
 *
 * @param string $expression an expression
 *
 * @return Expression
 */
function expr($expression)
{
    return new Expression($expression);
}
