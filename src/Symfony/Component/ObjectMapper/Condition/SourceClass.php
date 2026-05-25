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

/**
 * @template T of object
 *
 * @implements ClassRuleConditionCallableInterface<T, object>
 */
final class SourceClass implements ClassRuleConditionCallableInterface
{
    /**
     * @var non-empty-array<class-string>
     */
    private readonly array $sources;

    /**
     * @param class-string<T>|array<class-string<T>> $className
     */
    public function __construct(string|array $className)
    {
        $this->sources = \is_array($className) ? $className : [$className];
    }

    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        foreach ($this->sources as $validSource) {
            if ($source instanceof $validSource) {
                return true;
            }
        }

        return false;
    }
}
