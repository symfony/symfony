<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Condition;

use Symfony\Component\ObjectMapper\ConditionCallableInterface;

/**
 * @template T of object
 *
 * @implements ConditionCallableInterface<T, object>
 */
final class SourceClass implements ConditionCallableInterface
{
    /**
     * @param class-string<T> $className
     */
    public function __construct(private readonly string $className)
    {
    }

    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        return $source instanceof $this->className;
    }
}
