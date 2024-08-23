<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper;

use Symfony\Component\CallableWrapper\Attribute\CallableWrapperAttributeInterface;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolver;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolverInterface;

/**
 * Wraps a callable with all the CallableWrappers linked to it.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @experimental
 */
class CallableWrapper implements CallableWrapperInterface
{
    public function __construct(
        private readonly CallableWrapperResolverInterface $resolver = new CallableWrapperResolver([]),
    ) {
    }

    /**
     * @throws \ReflectionException
     */
    public function call(callable $callable, mixed ...$args): mixed
    {
        return $this->wrap($callable(...))(...$args);
    }

    /**
     * @throws \ReflectionException
     */
    public function wrap(\Closure $func): \Closure
    {
        foreach ($this->getAttributes($func) as $attribute) {
            $func = $this->resolver->resolve($attribute)->wrap($func, $attribute);
        }

        return $func;
    }

    /**
     * Extract all wrapper attributes from a given callable.
     *
     * @return iterable<CallableWrapperAttributeInterface>
     *
     * @throws \ReflectionException
     */
    private function getAttributes(\Closure $func): iterable
    {
        $function = new \ReflectionFunction($func);

        $attributes = $function->getAttributes(CallableWrapperAttributeInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

        if (!$attributes && '__invoke' === $function->getName() && $class = $function->getClosureCalledClass()) {
            $attributes = $class->getAttributes(CallableWrapperAttributeInterface::class, \ReflectionAttribute::IS_INSTANCEOF);
        }

        foreach (array_reverse($attributes) as $attribute) {
            yield $attribute->newInstance();
        }
    }
}
