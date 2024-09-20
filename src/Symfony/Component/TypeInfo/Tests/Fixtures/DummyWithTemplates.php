<?php

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

/**
 * @template T of int|string
 * @template U
 */
final class DummyWithTemplates
{
    private int $price;

    /**
     * @template T of int|float
     * @template V
     *
     * @return T
     */
    public function getPrice(bool $inCents = false): int|float
    {
        return $inCents ? $this->price : $this->price / 100;
    }
}
