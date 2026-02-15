<?php

namespace Fixtures\Model;

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithNestedDictDummies
{
    /** @var array<string, DummyWithNestedDictDummies>  */
    public array $dummies = [];
}
