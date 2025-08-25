<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\HydrateObject;

class MagicMethods
{
    public function __isset($key): bool
    {
        return 'name' === $key;
    }

    public function __get(string $key): string
    {
        return match ($key) {
            'name' => 'test',
            default => throw new \LogicException($key),
        };
    }
}
