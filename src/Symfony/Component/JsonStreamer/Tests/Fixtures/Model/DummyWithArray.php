<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithArray
{
    /** @var ClassicDummy[] */
    public array $dummies;

    public string $customProperty;
}
