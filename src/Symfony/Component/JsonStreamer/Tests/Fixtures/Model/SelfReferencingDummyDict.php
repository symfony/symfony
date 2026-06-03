<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class SelfReferencingDummyDict
{
    /**
     * @var array<string, self>
     */
    public array $items = [];
}
