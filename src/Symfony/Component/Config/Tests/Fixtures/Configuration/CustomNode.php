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

    public function getDefaultValue()
    {
        return true;
    }

    public function normalize($value)
    {
        return $value;
    }

    public function merge($leftSide, $rightSide)
    {
        return array_merge($leftSide, $rightSide);
    }

    public function finalize($value)
    {
        return $value;
    }
}
