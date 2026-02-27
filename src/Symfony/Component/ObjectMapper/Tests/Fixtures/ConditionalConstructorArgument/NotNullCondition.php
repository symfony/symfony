<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalConstructorArgument;

use Symfony\Component\ObjectMapper\ConditionCallableInterface;

final class NotNullCondition implements ConditionCallableInterface
{
    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        return null !== $value;
    }
}
