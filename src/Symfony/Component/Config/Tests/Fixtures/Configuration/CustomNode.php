<?php


namespace Symfony\Component\Config\Tests\Fixtures\Configuration;

use Symfony\Component\Config\Definition\NodeInterface;

class CustomNode implements NodeInterface
{
    public function getName(): string
    {
        return 'custom_node';
    }

    public function getPath(): string
    {
        return 'custom';
    }

    public function isRequired(): bool
    {
        return false;
    }

    public function hasDefaultValue(): bool
    {
        return true;
    }

    public function getDefaultValue(): mixed
    {
        return true;
    }

    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    public function merge(mixed $leftSide, mixed $rightSide): mixed
    {
        return array_merge($leftSide, $rightSide);
    }

    public function finalize(mixed $value): mixed
    {
        return $value;
    }
}
