<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Attribute to tell which callable to give to an argument of type Closure.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireCallable extends AutowireInline
{
    /**
     * @param string|array|null $callable The callable to autowire
     * @param string|null       $service  The service containing the callable to autowire
     * @param string|null       $method   The method name that will be autowired
     * @param bool|class-string $lazy     Whether to use lazy-loading for this argument
     */
    public function __construct(
        string|array|null $callable = null,
        ?string $service = null,
        ?string $method = null,
        bool|string $lazy = false,
    ) {
        if (!(null !== $callable xor null !== $service)) {
            throw new LogicException('#[AutowireCallable] attribute must declare exactly one of $callable or $service.');
        }
        if (null === $service && null !== $method) {
            throw new LogicException('#[AutowireCallable] attribute cannot have a $method without a $service.');
        }

        Autowire::__construct($callable ?? [new Reference($service), $method ?? '__invoke'], lazy: $lazy);
    }

    public function buildDefinition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        return (new Definition($type = \is_string($this->lazy) ? $this->lazy : ($type ?: 'Closure')))
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([\is_array($value) ? $value + [1 => '__invoke'] : $value])
            ->setLazy($this->lazy || 'Closure' !== $type && 'callable' !== (string) $parameter->getType());
    }
}
