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

use Symfony\Component\ObjectMapper\Exception\InvalidArgumentException;

/**
 * @implements ClassRuleConditionCallableInterface<object, object>
 */
final class ClassRuleList implements ClassRuleConditionCallableInterface
{
    /**
     * @param non-empty-array<ClassRule|SourceClass|TargetClass> $rules
     */
    public function __construct(private array $rules)
    {
        if (!$this->rules) {
            throw new InvalidArgumentException('A ClassRuleList needs at least one rule.');
        }
    }

    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        return array_any($this->rules, static fn ($rule) => $rule($value, $source, $target));
    }
}
