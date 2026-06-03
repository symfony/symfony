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
 * @implements ClassRuleConditionCallableInterface<object, object>
 */
final class TargetClass implements ClassRuleConditionCallableInterface
{
    /**
     * @var non-empty-array<class-string>
     */
    private readonly array $targets;

    /**
     * @param class-string<T>|array<class-string<T>> $className
     */
    public function __construct(string|array $className)
    {
        $this->targets = \is_array($className) ? $className : [$className];
    }

    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        foreach ($this->targets as $validTarget) {
            if ($target instanceof $validTarget) {
                return true;
            }
        }

        return false;
    }
}
