<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints;

class InheritanceParent
{
    public string $name; // string
    protected int $age; // int
    protected float $height; // float
    private bool $handsome; // bool
    private bool $cute; // bool

    public function __construct(bool $cute)
    {
        $this->cute = $cute;
    }

    public function isCute(): bool
    {
        return $this->cute;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): void
    {
        $this->height = $height;
    }

    public function isHandsome(): bool
    {
        return $this->handsome;
    }

    public function setHandsome(bool $handsome): void
    {
        $this->handsome = $handsome;
    }
}
