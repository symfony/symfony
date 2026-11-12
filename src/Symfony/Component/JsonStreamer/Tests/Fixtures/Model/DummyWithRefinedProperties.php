<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithRefinedProperties
{
    /**
     * @var positive-int
     */
    public int $count = 1;

    /**
     * @var 'draft'|'published'
     */
    public string $status = 'draft';

    /**
     * @var non-zero-int|null
     */
    public ?int $offset = null;

    /**
     * @var list<int<0, 100>>
     */
    public array $scores = [];
}
