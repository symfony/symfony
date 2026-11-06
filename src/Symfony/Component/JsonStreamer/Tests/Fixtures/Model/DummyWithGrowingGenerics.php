<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

/**
 * @template T
 */
class DummyWithGrowingGenerics
{
    /**
     * @var list<DummyWithGrowingGenerics<list<T>>>
     */
    public array $children = [];
}
