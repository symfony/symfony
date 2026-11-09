<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

/**
 * @template T
 */
class DummyWithDoublingGenerics
{
    /**
     * @var list<DummyWithDoublingGenerics<array{a: T, b: T}>>
     */
    public array $children = [];
}
