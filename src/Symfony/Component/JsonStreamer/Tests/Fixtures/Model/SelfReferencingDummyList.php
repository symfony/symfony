<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class SelfReferencingDummyList
{
    /**
     * @var self[]
     */
    public array $items = [];
}
