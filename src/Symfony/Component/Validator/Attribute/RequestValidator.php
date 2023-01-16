<?php

namespace Symfony\Component\Validator\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
final class RequestValidator
{
    private string $class;
    private ?string $finalize;

    public function __construct(
        string $class,
        ?string $finalize = null
    )
    {
        $this->class = $class;
        $this->finalize = $finalize;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getFinalize(): ?string
    {
        return $this->finalize;
    }
}
