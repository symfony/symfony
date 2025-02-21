<?php

namespace Symfony\Component\ArgumentResolver;

final readonly class SourceValue
{
    const NOT_FOUND = 'notfound';

    public function __construct(private mixed $value)
    {
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public static function notFound(): self
    {
        return new self(static::NOT_FOUND);
    }
}
