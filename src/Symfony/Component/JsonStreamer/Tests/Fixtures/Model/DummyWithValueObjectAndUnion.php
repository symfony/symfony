<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithValueObjectAndUnion
{
    public \DateTimeInterface|bool $valueObjectOrBool;
}
