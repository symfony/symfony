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
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\ExpressionLanguage\Expression;

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
    return new ServiceLocatorArgument(AbstractConfigurator::processValue($values, true));
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
