<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator;

use Symfony\Component\Decorator\Attribute\DecoratorAttribute;
use Symfony\Component\Decorator\Resolver\DecoratorResolver;
use Symfony\Component\Decorator\Resolver\DecoratorResolverInterface;

/**
 * Wraps a callable with all the decorators linked to it.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @experimental
 */
class CallableDecorator implements DecoratorInterface
{
    public function __construct(
        private readonly DecoratorResolverInterface $resolver = new DecoratorResolver([]),
    ) {
    }

    public function call(callable $callable, mixed ...$args): mixed
    {
        return $this->decorate($callable(...))(...$args);
    }

    public function decorate(\Closure $func): \Closure
    {
        foreach ($this->getMetadata($func) as $metadata) {
            $func = $this->resolver->resolve($metadata)->decorate($func, $metadata);
        }

        return $func;
    }

    /**
     * @return iterable<DecoratorAttribute>
     */
    private function getMetadata(\Closure $func): iterable
    {
        $attributes = (new \ReflectionFunction($func))->getAttributes(DecoratorAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);

        foreach (array_reverse($attributes) as $attribute) {
            yield $attribute->newInstance();
        }
    }
}
