<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php80Dummy
{
    public mixed $mixedProperty;

    /**
     * @param string $promotedAndMutated
     */
    public function __construct(private mixed $promoted, private mixed $promotedAndMutated)
    {
    }

    public function getFoo(): array|null
    {
    }

    public function setBar(int|null $bar)
    {
    }

    public function setTimeout(int|float $timeout)
    {
    }

    public function getOptional(): int|float|null
    {
    }

    public function setString(string|\Stringable $string)
    {
    }

    public function setPayload(mixed $payload)
    {
    }

    public function getData(): mixed
    {
    }
}
