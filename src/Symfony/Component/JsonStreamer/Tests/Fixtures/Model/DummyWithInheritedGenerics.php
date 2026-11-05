<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

/**
 * @template T
 * @template U of object
 *
 * @extends DummyWithGenerics<U>
 */
class DummyWithInheritedGenerics extends DummyWithGenerics
{
    /**
     * @var T
     */
    public mixed $extra = null;
}
