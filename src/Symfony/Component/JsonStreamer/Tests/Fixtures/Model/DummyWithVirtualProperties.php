<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithVirtualProperties
{
    public string $name = 'Alice';
    public string $upperName { get => strtoupper($this->name); }
    public string $writeOnlyName { set (string $value) { $this->name = $value; } }
}
